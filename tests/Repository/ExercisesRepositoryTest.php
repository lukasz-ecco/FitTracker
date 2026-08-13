<?php

namespace App\Tests\Repository;

use App\Entity\ExerciseMuscle;
use App\Entity\Exercises;
use App\Entity\Muscles;
use App\Entity\BodyParts;
use App\Enum\MuscleActivationLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExercisesRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testFindAllWithMuscles(): void
    {
        $bodyPart = new BodyParts();
        $bodyPart->setName('Klatka piersiowa');
        
        $muscle = new Muscles();
        $muscle->setName('Klatka piersiowa większa');

        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Exercises')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Muscles')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\BodyParts')->execute();

        $exercises = $this->entityManager->getRepository(Exercises::class)->findAllWithMuscles();

        $this->assertIsArray($exercises);
    }

    public function testFindByGoalAndMaxDifficulty(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Exercises')->execute();

        $ex1 = new Exercises();
        $ex1->setName('Pompki');
        $ex1->setDifficulty(3);
        $ex1->setType('Izolacyjne');
        $ex1->setSupportedGoals([\App\Enum\TrainingGoalType::MUSCLE_GAIN]);
        $this->entityManager->persist($ex1);

        $ex2 = new Exercises();
        $ex2->setName('Bieganie');
        $ex2->setDifficulty(2);
        $ex2->setType('Wielostawowe');
        $ex2->setSupportedGoals([\App\Enum\TrainingGoalType::WEIGHT_LOSS]);
        $this->entityManager->persist($ex2);

        $ex3 = new Exercises();
        $ex3->setName('Martwy ciąg');
        $ex3->setDifficulty(8);
        $ex3->setType('Wielostawowe');
        $ex3->setSupportedGoals([\App\Enum\TrainingGoalType::MUSCLE_GAIN]);
        $this->entityManager->persist($ex3);

        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(Exercises::class);

        // Szukamy MUSCLE_GAIN do poziomu 6 (powinno zwrócić tylko Pompki, bo Martwy ciąg ma 8)
        $muscleGainSuggestions = $repository->findByGoalAndMaxDifficulty(\App\Enum\TrainingGoalType::MUSCLE_GAIN, 6);
        $this->assertCount(1, $muscleGainSuggestions);
        $this->assertSame('Pompki', $muscleGainSuggestions[0]->getName());

        // Szukamy WEIGHT_LOSS do poziomu 3 (powinno zwrócić Bieganie)
        $weightLossSuggestions = $repository->findByGoalAndMaxDifficulty(\App\Enum\TrainingGoalType::WEIGHT_LOSS, 3);
        $this->assertCount(1, $weightLossSuggestions);
        $this->assertSame('Bieganie', $weightLossSuggestions[0]->getName());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
