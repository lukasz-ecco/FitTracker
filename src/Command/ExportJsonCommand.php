<?php

namespace App\Command;

use App\Entity\Exercises;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:export-json',
    description: 'Eksportuje ćwiczenia do pliku JSON.',
)]
class ExportJsonCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();

        $sql = 'SELECT name, description FROM exercises ORDER BY id ASC';
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery()->fetchAllAssociative();

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $filePath = $this->projectDir . '/exercises_export.json';
        file_put_contents($filePath, $json);

        $io->success(sprintf('Wyeksportowano %d ćwiczeń do pliku %s', count($result), $filePath));

        return Command::SUCCESS;
    }
}
