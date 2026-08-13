<?php

namespace App\Tests\Controller;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrainingPlanControllerTest extends WebTestCase
{
    private function createAndLoginUser($client): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        $user = new User();
        $user->setEmail(uniqid('test_plan_', true) . '@example.com');
        $user->setPassword('testpassword');
        $user->setRoles(['ROLE_USER']);
        
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        return $user;
    }

    public function testIndexPageLoadsForAuthenticatedUser(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $client->request('GET', '/training-plan');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Twoje Treningi');
    }

    public function testShowPageAndAjaxUpdate(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Setup mock data
        $workout = new Workout();
        $workout->setName('Test Workout');
        $workout->setUser($user);
        $workout->setStatus('PLANNED');

        $exercise = new Exercises();
        $exercise->setName('Push up');
        $exercise->setDifficulty(1);
        $exercise->setType('Strength');

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->setWorkout($workout);
        $workoutExercise->setExercise($exercise);
        $workoutExercise->setOrderIndex(1);

        $set = new WorkoutExerciseSet();
        $set->setWorkoutExercise($workoutExercise);
        $set->setSetNumber(1);
        $set->setReps(10);
        $set->setWeight(0);
        $set->setCompleted(false);

        $entityManager->persist($workout);
        $entityManager->persist($exercise);
        $entityManager->persist($workoutExercise);
        $entityManager->persist($set);
        $entityManager->flush();

        // Test show page
        $client->request('GET', '/training-plan/' . $workout->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Test Workout');

        // Test AJAX update
        $client->request(
            'PATCH',
            '/training-plan/set/' . $set->getId() . '/update',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'isCompleted' => true,
                'reps' => 12,
                'weight' => 20.5
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);

        // Check if database updated
        $updatedSet = $entityManager->getRepository(WorkoutExerciseSet::class)->find($set->getId());
        $this->assertTrue($updatedSet->isCompleted());
        $this->assertSame(12, $updatedSet->getReps());
        $this->assertSame(20.5, $updatedSet->getWeight());
    }
}
