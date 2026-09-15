<?php

namespace App\Tests\Controller\Api;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkoutSpontaneousCreateTest extends WebTestCase
{
    private EntityManagerInterface $em;

    private function createAndLoginUser($client): User
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('user_spontaneous_' . uniqid() . '@example.com');
        $user->setPassword('password123');
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $user;
    }

    public function testGetLastExerciseHistoryFirstTime(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $exercise = new Exercises();
        $exercise->setName('Wyciskanie hantli ' . uniqid());
        $exercise->setDifficulty(2);
        $exercise->setType('strength');
        $this->em->persist($exercise);
        $this->em->flush();

        $client->request('GET', '/api/exercises/' . $exercise->getId() . '/last-history');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['hasHistory']);
        $this->assertCount(3, $data['sets']);
        foreach ($data['sets'] as $set) {
            $this->assertSame(8, $set['reps']);
            $this->assertNull($set['weight']);
        }
    }

    public function testGetLastExerciseHistoryWithPreviousCompletedWorkout(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $exercise = new Exercises();
        $exercise->setName('Martwy ciąg ' . uniqid());
        $exercise->setDifficulty(3);
        $exercise->setType('strength');
        $this->em->persist($exercise);

        // Previous completed workout
        $workout = new Workout();
        $workout->setUser($user);
        $workout->setName('Poprzedni trening');
        $workout->setStatus('COMPLETED');
        $workout->setDate(new \DateTime('2026-09-01 10:00:00'));
        $this->em->persist($workout);

        $we = new WorkoutExercise();
        $we->setWorkout($workout);
        $we->setExercise($exercise);
        $we->setOrderIndex(1);
        $this->em->persist($we);

        $set1 = new WorkoutExerciseSet();
        $set1->setSetNumber(1);
        $set1->setReps(10);
        $set1->setWeight(80.0);
        $we->addWorkoutExerciseSet($set1);
        $this->em->persist($set1);

        $set2 = new WorkoutExerciseSet();
        $set2->setSetNumber(2);
        $set2->setReps(8);
        $set2->setWeight(90.0);
        $we->addWorkoutExerciseSet($set2);
        $this->em->persist($set2);

        $set3 = new WorkoutExerciseSet();
        $set3->setSetNumber(3);
        $set3->setReps(6);
        $set3->setWeight(100.0);
        $we->addWorkoutExerciseSet($set3);
        $this->em->persist($set3);

        $this->em->flush();
        $this->em->clear();

        $client->request('GET', '/api/exercises/' . $exercise->getId() . '/last-history');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['hasHistory']);
        $this->assertCount(3, $data['sets']);

        $this->assertSame(1, $data['sets'][0]['setNumber']);
        $this->assertSame(10, $data['sets'][0]['reps']);
        $this->assertEquals(80.0, $data['sets'][0]['weight']);

        $this->assertSame(2, $data['sets'][1]['setNumber']);
        $this->assertSame(8, $data['sets'][1]['reps']);
        $this->assertEquals(90.0, $data['sets'][1]['weight']);

        $this->assertSame(3, $data['sets'][2]['setNumber']);
        $this->assertSame(6, $data['sets'][2]['reps']);
        $this->assertEquals(100.0, $data['sets'][2]['weight']);
    }

    public function testGetLastExerciseHistoryIgnoresUncompletedDraftWorkout(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $exercise = new Exercises();
        $exercise->setName('Przysiad bułgarski ' . uniqid());
        $exercise->setDifficulty(2);
        $exercise->setType('strength');
        $this->em->persist($exercise);

        // Workout is DRAFT, not COMPLETED
        $workout = new Workout();
        $workout->setUser($user);
        $workout->setName('Trening w toku / draft');
        $workout->setStatus('DRAFT');
        $workout->setDate(new \DateTime());
        $this->em->persist($workout);

        $we = new WorkoutExercise();
        $we->setWorkout($workout);
        $we->setExercise($exercise);
        $we->setOrderIndex(1);
        $this->em->persist($we);

        $set1 = new WorkoutExerciseSet();
        $set1->setWorkoutExercise($we);
        $set1->setSetNumber(1);
        $set1->setReps(12);
        $set1->setWeight(20.0);
        $this->em->persist($set1);

        $this->em->flush();

        // Must ignore DRAFT and return first time defaults
        $client->request('GET', '/api/exercises/' . $exercise->getId() . '/last-history');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['hasHistory']);
        $this->assertCount(3, $data['sets']);
        $this->assertSame(8, $data['sets'][0]['reps']);
        $this->assertNull($data['sets'][0]['weight']);
    }

    public function testCreateWorkoutWithInitialExercisesAndSets(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $ex1 = new Exercises();
        $ex1->setName('Podciąganie ' . uniqid());
        $ex1->setDifficulty(3);
        $ex1->setType('strength');
        $this->em->persist($ex1);

        $ex2 = new Exercises();
        $ex2->setName('Pompki na poręczach ' . uniqid());
        $ex2->setDifficulty(2);
        $ex2->setType('strength');
        $this->em->persist($ex2);

        $this->em->flush();

        $payload = [
            'name' => 'Spontaniczny Trening A',
            'description' => 'Mój szybki trening',
            'exercises' => [
                [
                    'exerciseId' => $ex1->getId(),
                    'notes' => 'Szeroki chwyt',
                    'sets' => [
                        ['setNumber' => 1, 'reps' => 8, 'weight' => null],
                        ['setNumber' => 2, 'reps' => 8, 'weight' => null],
                        ['setNumber' => 3, 'reps' => 8, 'weight' => null],
                    ],
                ],
                [
                    'exerciseId' => $ex2->getId(),
                    'notes' => 'Z ciężarem',
                    'sets' => [
                        ['setNumber' => 1, 'reps' => 10, 'weight' => 15.0],
                        ['setNumber' => 2, 'reps' => 8, 'weight' => 20.0],
                    ],
                ],
            ],
        ];

        $client->request('POST', '/api/workouts', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(201);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Spontaniczny Trening A', $response['name']);
        $this->assertNotEmpty($response['id']);
        $this->assertArrayHasKey('workoutExercises', $response);
        $this->assertCount(2, $response['workoutExercises']);

        $firstEx = $response['workoutExercises'][0];
        $this->assertSame('Szeroki chwyt', $firstEx['notes']);
        $this->assertCount(3, $firstEx['workoutExerciseSets']);
        $this->assertEquals(0.0, $firstEx['workoutExerciseSets'][0]['weight']);
        $this->assertSame(8, $firstEx['workoutExerciseSets'][0]['reps']);

        $secondEx = $response['workoutExercises'][1];
        $this->assertSame('Z ciężarem', $secondEx['notes']);
        $this->assertCount(2, $secondEx['workoutExerciseSets']);
        $this->assertEquals(15.0, $secondEx['workoutExerciseSets'][0]['weight']);
        $this->assertEquals(20.0, $secondEx['workoutExerciseSets'][1]['weight']);
    }
}
