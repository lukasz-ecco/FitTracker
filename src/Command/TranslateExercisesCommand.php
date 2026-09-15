<?php

namespace App\Command;

use App\Entity\Exercises;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:translate-exercises',
    description: 'Tłumaczy nazwy i opisy ćwiczeń na język polski przy użyciu DeepL API.',
)]
class TranslateExercisesCommand extends Command
{
    private const BATCH_SIZE = 10;
    private string $stateFile;

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
        $this->stateFile = $this->projectDir . '/var/translated_exercises.json';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $apiKey = $_ENV['DEEPL_API_KEY'] ?? null;
        if (!$apiKey) {
            $io->error('Brak klucza API DeepL. Dodaj DEEPL_API_KEY do pliku .env');
            return Command::FAILURE;
        }

        $apiUrl = str_ends_with($apiKey, ':fx') 
            ? 'https://api-free.deepl.com/v2/translate' 
            : 'https://api.deepl.com/v2/translate';

        $io->title('Tłumaczenie ćwiczeń na język polski (DeepL)');

        // Wczytanie stanu (żeby nie tłumaczyć dwa razy tych samych)
        $translatedIds = file_exists($this->stateFile) 
            ? json_decode(file_get_contents($this->stateFile), true) 
            : [];

        if (!is_array($translatedIds)) {
            $translatedIds = [];
        }

        $exercisesRepo = $this->em->getRepository(Exercises::class);
        $allExercises = $exercisesRepo->findAll();

        $toTranslate = [];
        foreach ($allExercises as $exercise) {
            if (!in_array($exercise->getId(), $translatedIds)) {
                $toTranslate[] = $exercise;
            }
        }

        $total = count($toTranslate);
        if ($total === 0) {
            $io->success('Wszystkie ćwiczenia są już przetłumaczone (lub oznaczone jako przetłumaczone w var/translated_exercises.json)!');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Do przetłumaczenia pozostało: %d ćwiczeń.', $total));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();

        $chunks = array_chunk($toTranslate, self::BATCH_SIZE);
        $translatedCount = 0;
        $failedCount = 0;

        foreach ($chunks as $chunk) {
            $texts = [];
            $map = [];
            $exercisesById = [];

            /** @var Exercises $exercise */
            foreach ($chunk as $exercise) {
                $exercisesById[$exercise->getId()] = $exercise;

                if (!empty($exercise->getName())) {
                    $texts[] = $exercise->getName();
                    $map[] = ['id' => $exercise->getId(), 'field' => 'name'];
                }

                if (!empty($exercise->getDescription())) {
                    $texts[] = $exercise->getDescription();
                    $map[] = ['id' => $exercise->getId(), 'field' => 'description'];
                }
            }

            if (empty($texts)) {
                foreach ($chunk as $ex) {
                    $translatedIds[] = $ex->getId();
                    $progressBar->advance();
                }
                continue;
            }

            try {
                $response = $this->httpClient->request('POST', $apiUrl, [
                    'headers' => [
                        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'text' => $texts,
                        'target_lang' => 'PL'
                    ]
                ]);

                $data = $response->toArray();
                
                if (!isset($data['translations'])) {
                    throw new \Exception('Brak klucza translations w odpowiedzi API.');
                }

                foreach ($data['translations'] as $i => $translation) {
                    $item = $map[$i];
                    $exercise = $exercisesById[$item['id']];
                    
                    if ($item['field'] === 'name') {
                        $exercise->setName($translation['text']);
                    } else {
                        $exercise->setDescription($translation['text']);
                    }
                }

                foreach ($chunk as $exercise) {
                    $this->em->persist($exercise);
                    $translatedIds[] = $exercise->getId();
                    $translatedCount++;
                    
                    $progressBar->setMessage($exercise->getName());
                    $progressBar->advance();
                }

                $this->em->flush();
                // Zapisujemy stan na bieżąco, w razie przerwania
                file_put_contents($this->stateFile, json_encode($translatedIds));

            } catch (\Exception $e) {
                $failedCount += count($chunk);
                $progressBar->setMessage('BŁĄD: ' . $e->getMessage());
                // Przerywamy, bo błąd API (np. brak quota) nie powinien przechodzić w pętli
                $io->newLine(2);
                $io->error('Błąd z DeepL API: ' . $e->getMessage());
                break;
            }

            // Oszczędzamy limit uderzeń
            usleep(200_000); 
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->success([
            'Zakończono tłumaczenie!',
            'Przetłumaczono: ' . $translatedCount,
            'Zakończone błędem: ' . $failedCount
        ]);

        return Command::SUCCESS;
    }
}
