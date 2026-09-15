<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrainingPlanApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    private function createAndLoginUser($client, array $roles = ['ROLE_USER']): User
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('plan_test_' . uniqid() . '@example.com');
        $user->setPassword('password');
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $user;
    }

    public function testCreateAndGetTrainingPlan(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $payload = [
            'name' => 'Cykl Siłowy 3-dniowy',
            'description' => 'Budowanie siły i masy',
            'isActive' => true,
            'workouts' => [
                [
                    'name' => 'Dzień 1 - Klatka + Triceps',
                    'dayNumber' => 1,
                    'isRestDay' => false,
                    'notes' => 'Duży ciężar, przerwy 3 min'
                ],
                [
                    'name' => 'Dzień 2 - Regeneracja',
                    'dayNumber' => 2,
                    'isRestDay' => true,
                    'notes' => 'Spacer 10k kroków, sauna'
                ]
            ]
        ];

        $client->request('POST', '/api/training-plans', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(201);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Cykl Siłowy 3-dniowy', $response['name']);
        $this->assertTrue($response['isActive']);
        $this->assertCount(2, $response['workouts']);
        $planId = $response['id'];

        // GET /api/training-plans
        $client->request('GET', '/api/training-plans');
        $this->assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(1, count($list));

        // GET /api/training-plans/active
        $client->request('GET', '/api/training-plans/active');
        $this->assertResponseIsSuccessful();
        $activePlan = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($planId, $activePlan['id']);

        // POST /api/training-plans/{id}/workouts - dodanie kolejnego dnia
        $client->request('POST', "/api/training-plans/{$planId}/workouts", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Dzień 3 - Plecy + Biceps',
            'dayNumber' => 3,
            'isRestDay' => false,
            'notes' => 'Wiosłowanie i podciąganie'
        ]));
        $this->assertResponseStatusCodeSame(201);
        $addedWorkout = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Dzień 3 - Plecy + Biceps', $addedWorkout['name']);
        $this->assertEquals(3, $addedWorkout['dayNumber']);
        $this->assertFalse($addedWorkout['isRestDay']);
        $workoutId = $addedWorkout['id'];

        // PATCH /api/training-plans/workouts/{workoutId} - edycja dnia
        $client->request('PATCH', "/api/training-plans/workouts/{$workoutId}", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Dzień 3 - Zaktualizowany',
            'description' => 'Nowe zalecenia do treningu'
        ]));
        $this->assertResponseIsSuccessful();
        $updatedWorkout = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Dzień 3 - Zaktualizowany', $updatedWorkout['name']);
        $this->assertEquals('Nowe zalecenia do treningu', $updatedWorkout['description']);

        // DELETE /api/training-plans/workouts/{workoutId} - usunięcie dnia
        $client->request('DELETE', "/api/training-plans/workouts/{$workoutId}");
        $this->assertResponseStatusCodeSame(204);
    }
}
