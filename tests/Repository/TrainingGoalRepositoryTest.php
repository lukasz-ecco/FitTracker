<?php

namespace App\Tests\Repository;

use App\Entity\TrainingGoal;
use App\Entity\User;
use App\Enum\TrainingGoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TrainingGoalRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
    }

    public function testFindActiveGoalsByUser(): void
    {
        // Tworzymy unikalnego użytkownika testowego
        $user = new User();
        $user->setEmail('repo_test_' . uniqid() . '@example.com');
        $user->setPassword('password123');
        $user->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(TrainingGoal::class);

        // 1. Brak celów -> powinno zwrócić pustą tablicę
        $activeGoals = $repository->findActiveGoalsByUser($user);
        $this->assertEmpty($activeGoals);

        // 2. Dodajemy cel nieaktywny
        $inactiveGoal = new TrainingGoal();
        $inactiveGoal->setUser($user);
        $inactiveGoal->setGoalType(TrainingGoalType::WEIGHT_LOSS);
        $inactiveGoal->setFitnessLevel(1);
        $inactiveGoal->setIsActive(false);
        $this->entityManager->persist($inactiveGoal);

        // 3. Dodajemy cel aktywny 1
        $activeGoalEntity1 = new TrainingGoal();
        $activeGoalEntity1->setUser($user);
        $activeGoalEntity1->setGoalType(TrainingGoalType::MUSCLE_GAIN);
        $activeGoalEntity1->setFitnessLevel(2);
        $activeGoalEntity1->setIsActive(true);
        $this->entityManager->persist($activeGoalEntity1);

        // 4. Dodajemy cel aktywny 2
        $activeGoalEntity2 = new TrainingGoal();
        $activeGoalEntity2->setUser($user);
        $activeGoalEntity2->setGoalType(TrainingGoalType::STRENGTH);
        $activeGoalEntity2->setFitnessLevel(3);
        $activeGoalEntity2->setIsActive(true);
        $this->entityManager->persist($activeGoalEntity2);

        $this->entityManager->flush();

        // Sprawdzamy, czy zwraca oba aktywne
        $foundGoals = $repository->findActiveGoalsByUser($user);
        $this->assertCount(2, $foundGoals);
        
        $types = array_map(fn ($g) => $g->getGoalType(), $foundGoals);
        $this->assertContains(TrainingGoalType::MUSCLE_GAIN, $types);
        $this->assertContains(TrainingGoalType::STRENGTH, $types);
    }

    public function testFindAllByUser(): void
    {
        $user = new User();
        $user->setEmail('repo_test2_' . uniqid() . '@example.com');
        $user->setPassword('password123');
        $user->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(TrainingGoal::class);

        $goal1 = new TrainingGoal();
        $goal1->setUser($user);
        $goal1->setGoalType(TrainingGoalType::STRENGTH);
        $goal1->setFitnessLevel(3);
        $goal1->setIsActive(false);
        $this->entityManager->persist($goal1);

        $goal2 = new TrainingGoal();
        $goal2->setUser($user);
        $goal2->setGoalType(TrainingGoalType::ENDURANCE);
        $goal2->setFitnessLevel(2);
        $goal2->setIsActive(true);
        $this->entityManager->persist($goal2);

        $this->entityManager->flush();

        $allGoals = $repository->findAllByUser($user);
        $this->assertCount(2, $allGoals);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
