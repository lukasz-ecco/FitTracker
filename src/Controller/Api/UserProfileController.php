<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
{
    /**
     * Zwraca pełne dane profilu zalogowanego użytkownika.
     */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Zwraca dane zalogowanego użytkownika',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:read']))
    )]
    #[OA\Tag(name: 'Profile')]
    #[Security(name: 'Bearer')]
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
    #[OA\RequestBody(
        description: 'Dane do aktualizacji profilu',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'age', type: 'integer', example: 30, nullable: true),
                new OA\Property(property: 'gender', type: 'integer', example: 1, nullable: true),
                new OA\Property(property: 'height', type: 'integer', example: 180, nullable: true),
                new OA\Property(property: 'weight', type: 'integer', example: 80, nullable: true),
                new OA\Property(property: 'profilePictureBase64', type: 'string', example: 'data:image/png;base64,...', nullable: true),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Zwraca zaktualizowane dane zalogowanego użytkownika',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Profil zaktualizowany pomyślnie.'),
                new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:read'])),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 400, description: 'Nieprawidłowe dane JSON.')]
    #[OA\Tag(name: 'Profile')]
    #[Security(name: 'Bearer')]
    public function update(Request $request, UserProfileService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $updated = $service->updateProfile($user, $data);

        if (!$updated) {
            return new JsonResponse([
                'error' => 'Nie przekazano żadnych pól do aktualizacji.',
                'updatable_fields' => $service->getUpdatableFields(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Profil zaktualizowany pomyślnie.',
            'user' => $user,
        ], Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }
}
