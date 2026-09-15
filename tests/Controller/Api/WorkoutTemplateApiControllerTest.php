<?php

namespace App\Tests\Controller\Api;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkoutTemplateApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    private function createAndLoginUser($client, array $roles = ['ROLE_USER']): User
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('template_test_' . uniqid() . '@example.com');
        $user->setPassword('password');
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $user;
    }

    private function getOrCreateExercise(): Exercises
    {
        $exercise = $this->em->getRepository(Exercises::class)->findOneBy([]);
        if (!$exercise) {
            $exercise = new Exercises();
            $exercise->setName('Wyciskanie sztangi leżąc');
            $exercise->setDifficulty(3);
            $exercise->setType('Siłowe');
            $this->em->persist($exercise);
            $this->em->flush();
        }

        return $exercise;
    }

    public function testCreateTemplateFromScratch(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);
        $exercise = $this->getOrCreateExercise();

        $payload = [
            'name' => 'Push A - Klatka i Triceps',
            'description' => 'Główny dzień klatkowy',
            'exercises' => [
                [
                    'exerciseId' => $exercise->getId(),
                    'orderIndex' => 1,
                    'notes' => 'Pauza na dole 1s',
                    'sets' => [
                        ['setNumber' => 1, 'reps' => 10, 'weight' => 80.0, 'tempo' => '2-0-1-0'],
                        ['setNumber' => 2, 'reps' => 8, 'weight' => 85.0, 'tempo' => '2-0-1-0'],
                    ]
                ]
            ]
        ];

        $client->request('POST', '/api/workout-templates', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(201);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Push A - Klatka i Triceps', $response['name']);
        $this->assertEquals('Główny dzień klatkowy', $response['description']);
        $this->assertCount(1, $response['exercises']);
        $this->assertCount(2, $response['exercises'][0]['sets']);
        $templateId = $response['id'];

        // GET /api/workout-templates
        $client->request('GET', '/api/workout-templates');
        $this->assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(1, count($list));

        // GET /api/workout-templates/{id}
        $client->request('GET', "/api/workout-templates/{$templateId}");
        $this->assertResponseIsSuccessful();
        $single = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($templateId, $single['id']);
    }

    public function testCreateTemplateFromWorkoutPreservesOriginalWorkout(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);
        $exercise = $this->getOrCreateExercise();

        // 1. Tworzymy historyczny, ukończony trening
        $workout = new Workout();
        $workout->setName('Trening z wtorku');
        $workout->setDescription('Mocna sesja nóg');
        $workout->setUser($user);
        $workout->setStatus('COMPLETED');
        $workout->setDate(new \DateTime('2026-09-01'));
        $workout->setDuration(60);
        $workout->setVolume(1200.0);

        $we = new WorkoutExercise();
        $we->setExercise($exercise);
        $we->setOrderIndex(1);
        $we->setNotes('Głęboki przysiad');
        $workout->addWorkoutExercise($we);

        $set1 = new WorkoutExerciseSet();
        $set1->setSetNumber(1);
        $set1->setReps(12);
        $set1->setWeight(100.0);
        $set1->setIsCompleted(true);
        $we->addWorkoutExerciseSet($set1);

        $this->em->persist($workout);
        $this->em->flush();

        $originalWorkoutId = $workout->getId();

        // 2. Tworzymy szablon na podstawie tego treningu
        $client->request('POST', "/api/workout-templates/from-workout/{$originalWorkoutId}", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Szablon Dnia Nóg'
        ]));
        $this->assertResponseStatusCodeSame(201);

        $templateResponse = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Szablon Dnia Nóg', $templateResponse['name']);
        $this->assertCount(1, $templateResponse['exercises']);
        $this->assertCount(1, $templateResponse['exercises'][0]['sets']);
        $this->assertEquals(12, $templateResponse['exercises'][0]['sets'][0]['reps']);
        $this->assertEquals(100.0, $templateResponse['exercises'][0]['sets'][0]['weight']);

        // 3. Oryginalny trening w historii nadal istnieje i jest niezmieniony
        $client->request('GET', "/api/workouts/{$originalWorkoutId}");
        $this->assertResponseIsSuccessful();
        $workoutResponse = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Trening z wtorku', $workoutResponse['name']);
        $this->assertEquals('COMPLETED', $workoutResponse['status']);
        $this->assertEquals(1200.0, $workoutResponse['volume']);
    }

    public function testAddTemplateToTrainingPlan(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);
        $exercise = $this->getOrCreateExercise();

        // 1. Utworzenie szablonu
        $templatePayload = [
            'name' => 'FBW B',
            'description' => 'Całe ciało zestaw B',
            'exercises' => [
                [
                    'exerciseId' => $exercise->getId(),
                    'orderIndex' => 1,
                    'notes' => 'RPE 8',
                    'sets' => [
                        ['setNumber' => 1, 'reps' => 8, 'weight' => 70.0],
                        ['setNumber' => 2, 'reps' => 8, 'weight' => 75.0],
                        ['setNumber' => 3, 'reps' => 8, 'weight' => 75.0],
                    ]
                ]
            ]
        ];

        $client->request('POST', '/api/workout-templates', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($templatePayload));
        $this->assertResponseStatusCodeSame(201);
        $templateData = json_decode($client->getResponse()->getContent(), true);
        $templateId = $templateData['id'];

        // 2. Utworzenie planu treningowego
        $planPayload = [
            'name' => 'Nowy Plan FBW',
            'isActive' => true,
            'workouts' => [
                [
                    'name' => 'Dzień 1 - FBW B',
                    'dayNumber' => 1,
                    'templateId' => $templateId,
                    'isRestDay' => false
                ]
            ]
        ];

        $client->request('POST', '/api/training-plans', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($planPayload));
        $this->assertResponseStatusCodeSame(201);
        $planData = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $planData['workouts']);
        $day1 = $planData['workouts'][0];
        $this->assertEquals('Dzień 1 - FBW B', $day1['name']);

        // Sprawdzamy szczegóły planu
        $client->request('GET', "/api/training-plans/{$planData['id']}");
        $this->assertResponseIsSuccessful();
        $fullPlan = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $fullPlan['workouts']);
        $this->assertCount(1, $fullPlan['workouts'][0]['workoutExercises']);
        $this->assertCount(3, $fullPlan['workouts'][0]['workoutExercises'][0]['workoutExerciseSets']);
    }
}
