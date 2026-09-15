<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JwtRefreshTokenTest extends WebTestCase
{
    private EntityManagerInterface $em;

    public function testLoginReturnsTokenAndRefreshTokenAndCanRefresh(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'jwt_user_' . uniqid() . '@example.com';
        $password = 'TestPassword123!';

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        // 1. Test /api/login
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $this->assertResponseIsSuccessful();
        $loginData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $loginData);
        $this->assertArrayHasKey('refresh_token', $loginData);
        $this->assertNotEmpty($loginData['token']);
        $this->assertNotEmpty($loginData['refresh_token']);

        $originalToken = $loginData['token'];
        $refreshToken = $loginData['refresh_token'];

        // 2. Test /api/token/refresh
        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken,
        ]));

        $this->assertResponseIsSuccessful();
        $refreshData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $refreshData);
        $this->assertNotEmpty($refreshData['token']);

        $newToken = $refreshData['token'];

        // 3. Test protected endpoint with new token
        $client->request('GET', '/api/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $newToken,
            'CONTENT_TYPE' => 'application/json',
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testRefreshWithInvalidTokenFails(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => 'invalid_refresh_token_12345',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }
}
