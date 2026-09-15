<?php

namespace App\Tests\Controller\Api;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkoutControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createAndLoginUser($client): User
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('test_workout_' . uniqid() . '@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $user;
    }

    public function testCompletedWorkoutCannotBeModified(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $exercise = new Exercises();
        $exercise->setName('Wyciskanie sztangi ' . uniqid());
        $exercise->setDifficulty(3);
        $exercise->setType('strength');
        $this->em->persist($exercise);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setName('Trening testowy');
        $workout->setStatus('COMPLETED');
        $workout->setDate(new \DateTime());
        $this->em->persist($workout);

        $we = new WorkoutExercise();
        $we->setWorkout($workout);
        $we->setExercise($exercise);
        $we->setOrderIndex(1);
        $this->em->persist($we);

        $set = new WorkoutExerciseSet();
        $set->setWorkoutExercise($we);
        $set->setSetNumber(1);
        $set->setReps(10);
        $set->setWeight(80.0);
        $this->em->persist($set);

        $this->em->flush();

        // 1. Try to update workout
        $client->request('PATCH', '/api/workouts/' . $workout->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Nowa nazwa'
        ]));
        $this->assertResponseStatusCodeSame(400);
        $res = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Nie można modyfikować zakończonego treningu.', $res['error'] ?? null);

        // 2. Try to add exercise to workout
        $client->request('POST', '/api/workouts/' . $workout->getId() . '/exercises', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'exerciseId' => $exercise->getId()
        ]));
        $this->assertResponseStatusCodeSame(400);
        $res = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Nie można modyfikować zakończonego treningu.', $res['error'] ?? null);

        // 3. Try to add set to exercise
        $client->request('POST', '/api/workouts/exercises/' . $we->getId() . '/sets', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'reps' => 12,
            'weight' => 85.0
        ]));
        $this->assertResponseStatusCodeSame(400);
        $res = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Nie można modyfikować zakończonego treningu.', $res['error'] ?? null);

        // 4. Try to update set
        $client->request('PATCH', '/api/workouts/sets/' . $set->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'reps' => 15
        ]));
        $this->assertResponseStatusCodeSame(400);
        $res = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Nie można modyfikować zakończonego treningu.', $res['error'] ?? null);
    }
}
