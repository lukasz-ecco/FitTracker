<?php

namespace App\Command;

use App\Entity\BodyParts;
use App\Entity\ExerciseMuscle;
use App\Entity\Exercises;
use App\Entity\Muscles;
use App\Enum\MuscleActivationLevel;
use App\Repository\BodyPartsRepository;
use App\Repository\ExercisesRepository;
use App\Repository\MusclesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-exercises',
    description: 'Importuje ćwiczenia z ExerciseDB API (RapidAPI) wraz z GIFami',
)]
class ImportExercisesCommand extends Command
{
    private const EXERCISEDB_BASE_URL = 'https://exercisedb.p.rapidapi.com';
    private const RAPIDAPI_HOST = 'exercisedb.p.rapidapi.com';
    private const GIF_DIRECTORY = 'exercise-gifs';
    private const BATCH_SIZE = 20;

    /** @var array<string, BodyParts> Lokalny cache partii mięśniowych */
    private array $bodyPartCache = [];

    /** @var array<string, Muscles> Lokalny cache mięśni */
    private array $muscleCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly ExercisesRepository $exercisesRepo,
        private readonly MusclesRepository $musclesRepo,
        private readonly BodyPartsRepository $bodyPartsRepo,
        #[Autowire('%kernel.project_dir%/public/uploads/' . self::GIF_DIRECTORY)]
        private readonly string $gifDirectory,
        #[Autowire('%env(EXERCISEDB_API_KEY)%')]
        private readonly string $apiKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maksymalna liczba ćwiczeń do pobrania (0 = wszystkie)', 0)
            ->addOption('offset', 'o', InputOption::VALUE_OPTIONAL, 'Pomiń pierwsze N ćwiczeń z API (wznawianie importu)', 0)
            ->addOption('body-part', 'b', InputOption::VALUE_OPTIONAL, 'Filtruj po partii (np. chest, back, upper legs)')
            ->addOption('skip-gifs', null, InputOption::VALUE_NONE, 'Pomiń pobieranie GIFów (tylko metadane)')
            ->addOption('gifs-only', null, InputOption::VALUE_NONE, 'Pobierz tylko brakujące GIFy dla istniejących ćwiczeń (nie zużywa API quota)')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Aktualizuj już istniejące ćwiczenia');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Import 1300+ ćwiczeń + GIFów wymaga więcej pamięci
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $io->title('Import ćwiczeń z ExerciseDB API');

        if (!is_dir($this->gifDirectory)) {
            mkdir($this->gifDirectory, 0755, true);
            $io->info("Utworzono katalog: {$this->gifDirectory}");
        }

        // Tryb pobierania tylko brakujących GIFów (bez API quota)
        if ($input->getOption('gifs-only')) {
            return $this->downloadMissingGifs($io, $output);
        }

        $limit    = (int) $input->getOption('limit');
        $startOffset = (int) $input->getOption('offset');
        $bodyPart = $input->getOption('body-part');
        $skipGifs = $input->getOption('skip-gifs');
        $update   = $input->getOption('update');

        $io->info('Pobieranie listy ćwiczeń z API...');

        try {
            $exercises = $this->fetchExercises($bodyPart, $limit, $startOffset);
        } catch (\Exception $e) {
            $io->error('Błąd połączenia z API: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $total = count($exercises);
        $io->info("Pobrano {$total} ćwiczeń z API.");

        if ($total === 0) {
            $io->warning('Brak ćwiczeń do importu.');
            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();

        $created = $updated = $skipped = $errors = 0;

        foreach ($exercises as $i => $exerciseData) {
            $progressBar->setMessage($exerciseData['name'] ?? '...');
            $progressBar->advance();

            try {
                $externalId   = $exerciseData['id'] ?? null;
                $normalizedName = ucwords(strtolower($exerciseData['name'] ?? 'Unknown'));

                // Warstwa 1: szukaj po externalId (API ID)
                $existing = $externalId
                    ? $this->exercisesRepo->findOneBy(['externalId' => $externalId])
                    : null;

                // Warstwa 2: szukaj po nazwie (dla ćwiczeń bez externalId)
                if (!$existing) {
                    $existing = $this->exercisesRepo->findOneBy(['name' => $normalizedName]);
                    if ($existing && $externalId && !$existing->getExternalId()) {
                        // Uzupełnij brakujący externalId dla starych rekordów
                        $existing->setExternalId($externalId);
                    }
                }

                if ($existing && !$update) {
                    $skipped++;
                    continue;
                }

                $exercise = $existing ?? new Exercises();

                $exercise->setExternalId($externalId);
                $exercise->setName($normalizedName);
                $exercise->setDifficulty($this->mapDifficulty($exerciseData['difficulty'] ?? 'beginner'));
                $exercise->setType($this->mapEquipmentToType($exerciseData['equipment'] ?? 'other'));

                $instructions = $exerciseData['instructions'] ?? [];
                if (!empty($instructions)) {
                    $exercise->setDescription(implode("\n", $instructions));
                }

                // Zawsze zapisujemy URL, chyba że pobieramy lokalnie
                if (!empty($exerciseData['gifUrl'])) {
                    if (!$skipGifs) {
                        $localPath = $this->downloadGif($exerciseData['gifUrl'], $externalId);
                        $exercise->setGifUrl($localPath
                            ? '/uploads/' . self::GIF_DIRECTORY . '/' . basename($localPath)
                            : $exerciseData['gifUrl']
                        );
                    } else {
                        // Jeśli pomijamy pobieranie, zapisz oryginalny URL z API, 
                        // żeby można było pobrać go później (np. przez --gifs-only)
                        $exercise->setGifUrl($exerciseData['gifUrl']);
                    }
                }

                $this->em->persist($exercise);
                // Partie mięśniowe tylko przy tworzeniu nowego
                // WAŻNE: persist musi być przed assignMuscles, bo ExerciseMuscle
                // trzyma referencję do $exercise - musi być zarządzany przez Doctrine
                if (!$existing) {
                    $this->em->flush(); // flush żeby exercise miał ID przed utworzeniem ExerciseMuscle
                    $this->assignMuscles($exercise, $exerciseData);
                }
                $existing ? $updated++ : $created++;

                // Flush + clear co BATCH_SIZE żeby zwolnić pamięć Doctrine UoW
                if (($i + 1) % self::BATCH_SIZE === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    // Po clear() cache lokalny wciąż trzyma ID - re-fetch z repo przy następnym użyciu
                    $this->bodyPartCache = [];
                    $this->muscleCache   = [];
                }
            } catch (\Exception $e) {
                $errors++;
                $io->newLine();
                $io->warning("Błąd przy '{$exerciseData['name']}': " . $e->getMessage());
            }
        }

        $this->em->flush();
        $progressBar->finish();
        $io->newLine(2);

        $io->success([
            "Import zakończony!",
            "✅ Dodane:         {$created}",
            "🔄 Zaktualizowane: {$updated}",
            "⏭️  Pominięte:      {$skipped}",
            "❌ Błędy:          {$errors}",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Pobiera brakujące GIFy dla ćwiczeń które mają zewnętrzny URL (http*).
     * Nie zużywa API quota RapidAPI – działa tylko na danych z naszej bazy.
     */
    private function downloadMissingGifs(SymfonyStyle $io, OutputInterface $output): int
    {
        $io->title('Pobieranie brakujących GIFów');

        // Pobieramy całą listę z repo
        $exercises = $this->exercisesRepo->findWithExternalGifUrl();
        $total     = count($exercises);

        if ($total === 0) {
            $io->success('Wszystkie ćwiczenia mają już lokalne GIFy!');
            return Command::SUCCESS;
        }

        $io->info("Znaleziono {$total} ćwiczeń bez lokalnego GIF-a.");

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();

        $downloaded = $failed = 0;

        foreach ($exercises as $i => $detachedExercise) {
            // Ponieważ używamy em->clear(), encje z tablicy zostają odpięte (detached) od Doctrine.
            // Zamiast pracować na odpiętej encji, pobieramy ją ponownie z bazy na podstawie ID.
            $exercise = $this->em->getRepository(Exercises::class)->find($detachedExercise->getId());
            
            if (!$exercise) {
                continue;
            }

            $progressBar->setMessage($exercise->getName());
            $progressBar->advance();

            // Nawet jeśli gifUrl jest pusty, pobieramy go bazując na externalId
            $localPath = $this->downloadGif($exercise->getGifUrl(), $exercise->getExternalId());

            if ($localPath) {
                $exercise->setGifUrl('/uploads/' . self::GIF_DIRECTORY . '/' . basename($localPath));
                // W symfony persist() dla uaktualnianych rekordów zazwyczaj nie jest potrzebny jeśli 
                // obiekt jest zarządzany (pobrany przez find), ale nie zaszkodzi.
                $downloaded++;
            } else {
                $failed++;
            }

            if (($i + 1) % self::BATCH_SIZE === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $progressBar->finish();
        $io->newLine(2);

        $io->success([
            'Pobieranie GIFów zakończone!',
            "✅ Pobrane:  {$downloaded}",
            "❌ Błędy:    {$failed}",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Pobiera ćwiczenia z ExerciseDB stronicując wyniki.
     *
     * Darmowy plan ExerciseDB zwraca max ~10 wyników per request, niezależnie
     * od parametru limit. Dlatego paginujemy offsetem i przerywamy TYLKO gdy
     * API zwróci pustą stronę – nie gdy zwróci mniej niż prosiliśmy.
     */
    private function fetchExercises(?string $bodyPart, int $limit, int $startOffset = 0): array
    {
        $all      = [];
        $pageSize = 10; // Dopasowany do limitu darmowego planu
        $offset   = $startOffset; // Zacznij od podanego offsetu
        $maxItems = $limit > 0 ? $limit : PHP_INT_MAX;

        $url = self::EXERCISEDB_BASE_URL . '/exercises';
        if ($bodyPart) {
            $url = self::EXERCISEDB_BASE_URL . '/exercises/bodyPart/' . urlencode($bodyPart);
        }

        while (count($all) < $maxItems) {
            $fetchLimit = min($pageSize, $maxItems - count($all));

            $data = $this->requestWithRetry('GET', $url, [
                'headers' => [
                    'X-RapidAPI-Key'  => $this->apiKey,
                    'X-RapidAPI-Host' => self::RAPIDAPI_HOST,
                ],
                'query' => [
                    'limit'  => $fetchLimit,
                    'offset' => $offset,
                ],
            ]);

            // Pusta strona = koniec danych
            if (empty($data)) {
                break;
            }

            $all     = array_merge($all, $data);
            $offset += count($data);

            // 300ms throttle żeby nie przekroczyć rate limitu API
            usleep(300_000);
        }

        return array_slice($all, 0, $maxItems);
    }

    /**
     * Wykonuje request HTTP z automatycznym retry przy HTTP 429.
     * Obsługuje nagłówek Retry-After z API.
     *
     * @throws \RuntimeException gdy wszystkie próby się nie powiodą
     */
    private function requestWithRetry(string $method, string $url, array $options = [], int $maxRetries = 5, bool $binary = false): array|string
    {
        $attempt = 0;

        while (true) {
            try {
                $response    = $this->httpClient->request($method, $url, $options);
                $statusCode  = $response->getStatusCode();

                if ($statusCode === 429) {
                    $attempt++;
                    if ($attempt > $maxRetries) {
                        throw new \RuntimeException("Rate limit przekroczony po {$maxRetries} próbach.");
                    }

                    // Sprawdź czy API podaje ile czekać
                    $retryAfter = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);
                    $waitMs     = $retryAfter > 0
                        ? $retryAfter * 1000          // API powiedziało ile
                        : (2 ** $attempt) * 1000;     // exponential backoff: 2s, 4s, 8s, 16s...

                    // Nie śpij dłużej niż 60 sekund
                    $waitMs = min($waitMs, 60_000);
                    usleep($waitMs * 1000);
                    continue;
                }

                if ($statusCode !== 200) {
                    throw new \RuntimeException("Zły status HTTP: $statusCode dla $url");
                }

                return $binary ? $response->getContent() : $response->toArray();

            } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
                $attempt++;
                if ($attempt > $maxRetries) {
                    throw new \RuntimeException("Błąd sieci po {$maxRetries} próbach: " . $e->getMessage());
                }
                $waitMs = (2 ** $attempt) * 1000;
                usleep($waitMs * 1000);
            }
        }
    }

    /**
     * Pobiera GIF i zapisuje lokalnie. Zwraca ścieżkę lub null przy błędzie.
     */
    private function downloadGif(?string $url, ?string $externalId): ?string
    {
        if (!$externalId) {
            return null;
        }

        try {
            $filename = "{$externalId}.gif";
            $filepath = $this->gifDirectory . '/' . $filename;

            if (file_exists($filepath)) {
                return $filepath; // Już pobrany wcześniej
            }

            // ExerciseDB API zmieniło zasady: gifUrl zniknęło, trzeba użyć specjalnego endpointu.
            $imageUrl = "https://exercisedb.p.rapidapi.com/image?exerciseId={$externalId}&resolution=360";

            $content = $this->requestWithRetry('GET', $imageUrl, [
                'headers' => [
                    'X-RapidAPI-Key'  => $this->apiKey,
                    'X-RapidAPI-Host' => self::RAPIDAPI_HOST,
                ],
                'timeout' => 20
            ], 5, true);

            file_put_contents($filepath, $content);
            return $filepath;

        } catch (\Exception $e) {
            // Jeśli to twardy błąd rate limit, przerywamy CAŁY proces od razu
            if (str_contains($e->getMessage(), 'Rate limit')) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Tworzy powiązania exercise -> muscles na podstawie danych z API.
     */
    private function assignMuscles(Exercises $exercise, array $data): void
    {
        $bodyPartName       = $data['bodyPart'] ?? 'other';
        $primaryMuscles     = isset($data['target']) ? [$data['target']] : [];
        $secondaryMuscles   = $data['secondaryMuscles'] ?? [];

        $bodyPart = $this->getOrCreateBodyPart($bodyPartName);

        foreach ($primaryMuscles as $muscleName) {
            $muscle = $this->getOrCreateMuscle($muscleName, $bodyPart);
            $this->createExerciseMuscle($exercise, $muscle, MuscleActivationLevel::HIGH);
        }

        foreach ($secondaryMuscles as $muscleName) {
            $muscle = $this->getOrCreateMuscle($muscleName, $bodyPart);
            $this->createExerciseMuscle($exercise, $muscle, MuscleActivationLevel::MEDIUM);
        }
    }

    private function getOrCreateBodyPart(string $name): BodyParts
    {
        $name = strtolower(trim($name));

        // Sprawdź lokalny cache (unika zbędnych query)
        if (isset($this->bodyPartCache[$name])) {
            return $this->bodyPartCache[$name];
        }

        $existing = $this->bodyPartsRepo->findOneBy(['name' => $name]);

        if (!$existing) {
            $existing = (new BodyParts())->setName($name);
            $this->em->persist($existing);
            $this->em->flush();
        }

        $this->bodyPartCache[$name] = $existing;
        return $existing;
    }

    private function getOrCreateMuscle(string $name, BodyParts $bodyPart): Muscles
    {
        $name = strtolower(trim($name));

        // Sprawdź lokalny cache (unika zbędnych query)
        if (isset($this->muscleCache[$name])) {
            return $this->muscleCache[$name];
        }

        $existing = $this->musclesRepo->findOneBy(['name' => $name]);

        if (!$existing) {
            $existing = (new Muscles())->setName($name)->setBodyPart($bodyPart);
            $this->em->persist($existing);
            $this->em->flush();
        }

        $this->muscleCache[$name] = $existing;
        return $existing;
    }

    private function createExerciseMuscle(
        Exercises $exercise,
        Muscles $muscle,
        MuscleActivationLevel $level
    ): void {
        foreach ($exercise->getExerciseMuscles() as $em) {
            if ($em->getMuscle() === $muscle) {
                return; // Już przypisany
            }
        }

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->setExercise($exercise);
        $exerciseMuscle->setMuscle($muscle);
        $exerciseMuscle->setActivationLevel($level);

        $this->em->persist($exerciseMuscle);
        $exercise->addExerciseMuscle($exerciseMuscle);
    }

    private function mapDifficulty(string $difficulty): int
    {
        return match (strtolower($difficulty)) {
            'beginner'            => 1,
            'intermediate'        => 2,
            'expert', 'advanced'  => 3,
            default               => 1,
        };
    }

    private function mapEquipmentToType(string $equipment): string
    {
        return match (strtolower($equipment)) {
            'barbell', 'dumbbell', 'kettlebell', 'cable',
            'machine', 'smith machine', 'ez barbell', 'olympic barbell' => 'Wielostawowe',
            default => 'Izolacyjne',
        };
    }
}
