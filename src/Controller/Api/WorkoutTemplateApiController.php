<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\WorkoutTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/workout-templates')]
#[IsGranted('ROLE_USER')]
class WorkoutTemplateApiController extends AbstractController
{
    #[Route('', name: 'api_workout_templates_index', methods: ['GET'])]
    public function index(WorkoutTemplateService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $templates = $service->getUserTemplates($user);

        return $this->json($templates, Response::HTTP_OK, [], ['groups' => ['template:read', 'template:read:full', 'exercise:read']]);
    }

    #[Route('/{id}', name: 'api_workout_templates_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, WorkoutTemplateService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $template = $service->getTemplate($id, $user);

        return $this->json($template, Response::HTTP_OK, [], ['groups' => ['template:read:full', 'exercise:read']]);
    }

    #[Route('', name: 'api_workout_templates_create', methods: ['POST'])]
    public function create(Request $request, WorkoutTemplateService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $template = $service->createTemplate($user, $data);
        return $this->json($template, Response::HTTP_CREATED, [], ['groups' => ['template:read:full', 'exercise:read']]);
    }

    #[Route('/from-workout/{workoutId}', name: 'api_workout_templates_create_from_workout', requirements: ['workoutId' => '\d+'], methods: ['POST'])]
    public function createFromWorkout(int $workoutId, Request $request, WorkoutTemplateService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?: [];

        $customName = $data['name'] ?? null;
        $template = $service->createTemplateFromWorkout($user, $workoutId, $customName);

        return $this->json($template, Response::HTTP_CREATED, [], ['groups' => ['template:read:full', 'exercise:read']]);
    }

    #[Route('/{id}', name: 'api_workout_templates_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function update(int $id, Request $request, WorkoutTemplateService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $template = $service->getTemplate($id, $user);

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $template = $service->updateTemplate($template, $user, $data);
        return $this->json($template, Response::HTTP_OK, [], ['groups' => ['template:read:full', 'exercise:read']]);
    }

    #[Route('/{id}', name: 'api_workout_templates_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, WorkoutTemplateService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $template = $service->getTemplate($id, $user);

        $service->deleteTemplate($template, $user);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
