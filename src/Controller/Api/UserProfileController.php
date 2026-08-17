<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
{
    /**
     * Zwraca pełne dane profilu zalogowanego użytkownika.
     */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($user, Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }

    /**
     * Aktualizuje dane profilu zalogowanego użytkownika.
     */
    #[Route('/me', name: 'api_me_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        // Aktualizacja dozwolonych pól profilu
        $updatableFields = [
            'name' => 'setName',
            'surrname' => 'setSurrname',
            'age' => 'setAge',
            'gender' => 'setGender',
            'height' => 'setHeight',
            'weight' => 'setWeight',
        ];

        $updated = false;
        foreach ($updatableFields as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $user->$setter($data[$field]);
                $updated = true;
            }
        }

        if (!$updated) {
            return new JsonResponse([
                'error' => 'Nie przekazano żadnych pól do aktualizacji.',
                'updatable_fields' => array_keys($updatableFields),
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'message' => 'Profil zaktualizowany pomyślnie.',
            'user' => $user,
        ], Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }
}
