<?php

namespace App\Tests\Repository;

use App\Entity\ExerciseMuscle;
use App\Entity\Exercises;
use App\Entity\Muscles;
use App\Entity\BodyParts;
use App\Entity\GoalType;
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

    private function cleanDatabase(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\WorkoutExerciseSet')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\WorkoutExercise')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Workout')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseSupportedGoal')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Exercises')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Muscles')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\BodyParts')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\TrainingGoal')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\GoalType')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testFindAllWithMuscles(): void
    {
        $this->cleanDatabase();

        $bodyPart = new BodyParts();
        $bodyPart->setName('Klatka piersiowa');
        
        $muscle = new Muscles();
        $muscle->setName('Klatka piersiowa większa');

        $exercises = $this->entityManager->getRepository(Exercises::class)->findAllWithMuscles();

        $this->assertIsArray($exercises);
    }

    public function testFindByGoalAndMaxDifficulty(): void
    {
        $this->cleanDatabase();

        $goalType1 = new GoalType();
        $goalType1->setName('muscle_gain');
        $goalType1->setLabel('Budowa masy');
        $this->entityManager->persist($goalType1);

        $goalType2 = new GoalType();
        $goalType2->setName('weight_loss');
        $goalType2->setLabel('Redukcja');
        $this->entityManager->persist($goalType2);

        $ex1 = new Exercises();
        $ex1->setName('Pompki');
        $ex1->setDifficulty(3);
        $ex1->setType('Izolacyjne');
        $this->entityManager->persist($ex1);

        $esg1 = new \App\Entity\ExerciseSupportedGoal();
        $esg1->setExercise($ex1)->setGoalType($goalType1);
        $this->entityManager->persist($esg1);

        $ex2 = new Exercises();
        $ex2->setName('Bieganie');
        $ex2->setDifficulty(2);
        $ex2->setType('Wielostawowe');
        $this->entityManager->persist($ex2);

        $esg2 = new \App\Entity\ExerciseSupportedGoal();
        $esg2->setExercise($ex2)->setGoalType($goalType2);
        $this->entityManager->persist($esg2);

        $ex3 = new Exercises();
        $ex3->setName('Martwy ciąg');
        $ex3->setDifficulty(8);
        $ex3->setType('Wielostawowe');
        $this->entityManager->persist($ex3);

        $esg3 = new \App\Entity\ExerciseSupportedGoal();
        $esg3->setExercise($ex3)->setGoalType($goalType1);
        $this->entityManager->persist($esg3);

        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(Exercises::class);

        // Szukamy muscle_gain do poziomu 6 (powinno zwrócić tylko Pompki, bo Martwy ciąg ma 8)
        $muscleGainSuggestions = $repository->findByGoalAndMaxDifficulty($goalType1, 6);
        $this->assertCount(1, $muscleGainSuggestions);
        $this->assertSame('Pompki', $muscleGainSuggestions[0]->getName());

        // Szukamy weight_loss do poziomu 3 (powinno zwrócić Bieganie)
        $weightLossSuggestions = $repository->findByGoalAndMaxDifficulty($goalType2, 3);
        $this->assertCount(1, $weightLossSuggestions);
        $this->assertSame('Bieganie', $weightLossSuggestions[0]->getName());
    }

    public function testSearchExercisesWithPopularityAndAlphabeticalOrdering(): void
    {
        $this->cleanDatabase();

        $user = new \App\Entity\User();
        $user->setEmail('user_' . uniqid() . '@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);

        // 4 exercises
        $exA = new Exercises();
        $exA->setName('Wyciskanie sztangi leżąc');
        $exA->setDifficulty(2);
        $exA->setType('Wielostawowe');
        $this->entityManager->persist($exA);

        $exB = new Exercises();
        $exB->setName('Wyciskanie żołnierskie');
        $exB->setDifficulty(3);
        $exB->setType('Wielostawowe');
        $this->entityManager->persist($exB);

        $exC = new Exercises();
        $exC->setName('Wyciskanie hantli skos');
        $exC->setDifficulty(2);
        $exC->setType('Izolacyjne');
        $this->entityManager->persist($exC);

        $exD = new Exercises();
        $exD->setName('Przysiad ze sztangą');
        $exD->setDifficulty(3);
        $exD->setType('Wielostawowe');
        $this->entityManager->persist($exD);

        $this->entityManager->flush();

        // Create workouts with workout exercises to simulate popularity:
        // exC has 3 usages, exB has 1 usage, exA has 0 usages, exD has 5 usages (but doesn't match 'wyciskanie')
        $workout1 = new \App\Entity\Workout();
        $workout1->setName('Trening A');
        $workout1->setUser($user);
        $workout1->setDate(new \DateTime());
        $workout1->setStatus('COMPLETED');
        $this->entityManager->persist($workout1);

        $workout2 = new \App\Entity\Workout();
        $workout2->setName('Trening B');
        $workout2->setUser($user);
        $workout2->setDate(new \DateTime());
        $workout2->setStatus('COMPLETED');
        $this->entityManager->persist($workout2);

        $workout3 = new \App\Entity\Workout();
        $workout3->setName('Trening C');
        $workout3->setUser($user);
        $workout3->setDate(new \DateTime());
        $workout3->setStatus('COMPLETED');
        $this->entityManager->persist($workout3);

        // Add 3 usages of exC
        $we1 = new \App\Entity\WorkoutExercise();
        $we1->setWorkout($workout1)->setExercise($exC)->setOrderIndex(1);
        $this->entityManager->persist($we1);

        $we2 = new \App\Entity\WorkoutExercise();
        $we2->setWorkout($workout2)->setExercise($exC)->setOrderIndex(1);
        $this->entityManager->persist($we2);

        $we3 = new \App\Entity\WorkoutExercise();
        $we3->setWorkout($workout3)->setExercise($exC)->setOrderIndex(1);
        $this->entityManager->persist($we3);

        // Add 1 usage of exB
        $we4 = new \App\Entity\WorkoutExercise();
        $we4->setWorkout($workout1)->setExercise($exB)->setOrderIndex(2);
        $this->entityManager->persist($we4);

        // Add 2 usages of exD
        $we5 = new \App\Entity\WorkoutExercise();
        $we5->setWorkout($workout1)->setExercise($exD)->setOrderIndex(3);
        $this->entityManager->persist($we5);
        $we6 = new \App\Entity\WorkoutExercise();
        $we6->setWorkout($workout2)->setExercise($exD)->setOrderIndex(2);
        $this->entityManager->persist($we6);

        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(Exercises::class);

        // Search 'wyciskanie'
        $results = $repository->searchExercises('wyciskanie', 1, 20);

        $this->assertCount(3, $results);
        // exC (3 uses) -> exB (1 use) -> exA (0 uses)
        $this->assertSame('Wyciskanie hantli skos', $results[0]->getName());
        $this->assertSame('Wyciskanie żołnierskie', $results[1]->getName());
        $this->assertSame('Wyciskanie sztangi leżąc', $results[2]->getName());

        // Search all exercises (no search query)
        $allResults = $repository->searchExercises(null, 1, 20);
        $this->assertCount(4, $allResults);
        // exC (3 uses) -> exD (2 uses) -> exB (1 use) -> exA (0 uses)
        $this->assertSame('Wyciskanie hantli skos', $allResults[0]->getName());
        $this->assertSame('Przysiad ze sztangą', $allResults[1]->getName());
        $this->assertSame('Wyciskanie żołnierskie', $allResults[2]->getName());
        $this->assertSame('Wyciskanie sztangi leżąc', $allResults[3]->getName());
    }

    public function testSearchExercisesPagination(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\WorkoutExerciseSet')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\WorkoutExercise')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Workout')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Exercises')->execute();

        for ($i = 1; $i <= 25; $i++) {
            $ex = new Exercises();
            $ex->setName(sprintf('Paginacja Ćwiczenie %02d', $i));
            $ex->setDifficulty(1);
            $ex->setType('Wielostawowe');
            $this->entityManager->persist($ex);
        }
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(Exercises::class);

        $page1 = $repository->searchExercises('Paginacja', 1, 20);
        $this->assertCount(20, $page1);
        $this->assertSame('Paginacja Ćwiczenie 01', $page1[0]->getName());
        $this->assertSame('Paginacja Ćwiczenie 20', $page1[19]->getName());

        $page2 = $repository->searchExercises('Paginacja', 2, 20);
        $this->assertCount(5, $page2);
        $this->assertSame('Paginacja Ćwiczenie 21', $page2[0]->getName());
        $this->assertSame('Paginacja Ćwiczenie 25', $page2[4]->getName());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager = null;
    }
}
