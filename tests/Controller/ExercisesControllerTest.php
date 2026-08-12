<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExercisesControllerTest extends WebTestCase
{
    private function createAndLoginUser($client): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Clean up previous test users if needed or just use a unique email
        $user = new User();
        $user->setEmail(uniqid('test_', true) . '@example.com');
        $user->setPassword('testpassword'); // Password doesn't need to be hashed for just logging in the test client
        $user->setRoles(['ROLE_USER']);
        
        $entityManager->persist($user);
        $entityManager->flush();

        // Simulate login
        $client->loginUser($user);
    }

    public function testIndex(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $crawler = $client->request('GET', '/exercises');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Lista ćwiczeń');
    }

    public function testAddExercisePageLoads(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $crawler = $client->request('GET', '/exercises/add');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dodaj nowe ćwiczenie');
        // Ensure the form is present
        $this->assertSelectorExists('form[name="exercise"]');
    }
}
