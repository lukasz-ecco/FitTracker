<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    private function createAndLoginUser($client, array $roles = ['ROLE_USER']): User
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('dash_test_' . uniqid() . '@example.com');
        $user->setPassword('password');
        $user->setRoles($roles);
        $user->setWeight(85);
        $user->setHeight(185);

        $this->em->persist($user);
        $this->em->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $user;
    }

    public function testGetDashboardSummary(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $client->request('GET', '/api/dashboard/summary');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('todayPlan', $data);
        $this->assertArrayHasKey('todayCompleted', $data);
        $this->assertArrayHasKey('weightSummary', $data);

        $this->assertEquals(85.0, $data['weightSummary']['currentWeight']);
        $this->assertEquals(185.0, $data['weightSummary']['height']);
        $this->assertNotNull($data['weightSummary']['bmi']);
    }

    public function testCreateAndGetWeightLogs(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $payload = [
            'weight' => 84.5,
            'date' => '2026-09-15',
            'notes' => 'Na czczo'
        ];

        $client->request('POST', '/api/weight-logs', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(201);

        $created = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(84.5, $created['weight']);
        $this->assertEquals('Na czczo', $created['notes']);

        // GET /api/weight-logs
        $client->request('GET', '/api/weight-logs');
        $this->assertResponseIsSuccessful();

        $list = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
        $this->assertEquals(84.5, $list[0]['weight']);

        // Sprawdzenie czy w /api/dashboard/summary waga zaktualizowała się
        $client->request('GET', '/api/dashboard/summary');
        $this->assertResponseIsSuccessful();
        $summary = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(84.5, $summary['weightSummary']['currentWeight']);
    }
}
