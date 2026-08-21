<?php

namespace App\Tests\Controller\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserProfileControllerTest extends ApiTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testUpdateProfile(): void
    {
        $client = static::createClient();

        // Create a test user
        $user = new User();
        $user->setEmail('test_profile_' . uniqid() . '@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        
        $this->em->persist($user);
        $this->em->flush();

        // Authenticate the user (assuming JWT authentication is set up this way, or we can use $client->loginUser)
        $client->loginUser($user);

        // Update the profile
        $response = $client->request('PATCH', '/api/me', [
            'json' => [
                'name' => 'Jan',
                'surrname' => 'Kowalski',
                'age' => 30,
                'height' => 180,
                'weight' => 80
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'message' => 'Profil zaktualizowany pomyślnie.',
            'user' => [
                'name' => 'Jan',
                'surrname' => 'Kowalski',
                'age' => 30,
                'height' => 180,
                'weight' => 80
            ]
        ]);
    }
}
