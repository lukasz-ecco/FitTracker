<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    /**
     * Ten endpoint jest obsługiwany przez json_login w security.yaml.
     * Symfony przechwytuje request zanim dotrze do metody kontrolera.
     * Metoda istnieje tylko po to, żeby zarejestrować route.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Nigdy nie zostanie wywołane — json_login przechwytuje request
        return new JsonResponse(['error' => 'Powinien być obsłużony przez json_login'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $accountType = $data['accountType'] ?? null;

        // Walidacja wymaganych pól
        if (!$email || !$password) {
            return new JsonResponse(['error' => 'Pola email i password są wymagane.'], Response::HTTP_BAD_REQUEST);
        }

        if ($password && strlen($password) < 6) {
            return new JsonResponse(['error' => 'Hasło musi mieć co najmniej 6 znaków.'], Response::HTTP_BAD_REQUEST);
        }

        // Sprawdzenie czy email jest już zajęty
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Użytkownik o podanym adresie email już istnieje.'], Response::HTTP_CONFLICT);
        }

        // Walidacja typu konta
        $allowedRoles = ['ROLE_TRAINER', 'ROLE_TRAINEE'];
        if ($accountType && !in_array($accountType, $allowedRoles)) {
            return new JsonResponse([
                'error' => 'Nieprawidłowy typ konta. Dozwolone: ROLE_TRAINER, ROLE_TRAINEE.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        if ($accountType) {
            $user->setRoles([$accountType]);
        }

        // Opcjonalne pola profilu
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }
        if (isset($data['surrname'])) {
            $user->setSurrname($data['surrname']);
        }

        // Walidacja encji
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Rejestracja zakończona pomyślnie.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ], Response::HTTP_CREATED);
    }
}
