<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ExercisesRepository;
use App\Service\WorkoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/exercises')]
#[IsGranted('ROLE_USER')]
class ExerciseController extends AbstractController
{
    #[Route('', name: 'api_exercises_list', methods: ['GET'])]
    public function index(
        Request $request,
        ExercisesRepository $repository,
        SerializerInterface $serializer
    ): JsonResponse {
        $search = $request->query->get('search') ?? $request->query->get('q');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        $exercises = $repository->searchExercises($search, $page, $limit);
        $json = $serializer->serialize($exercises, 'json', ['groups' => ['exercise:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_exercises_show', methods: ['GET'])]
    public function show(int $id, ExercisesRepository $repository): JsonResponse
    {
        $exercise = $repository->find($id);
        if (!$exercise) {
            return new JsonResponse(['error' => 'Ćwiczenie nie zostało znalezione.'], Response::HTTP_NOT_FOUND);
        }

        $muscles = [];
        foreach ($exercise->getExerciseMuscles() as $em) {
            $muscle = $em->getMuscle();
            if ($muscle) {
                $activationLevel = $em->getActivationLevel();
                $muscles[] = [
                    'id' => $muscle->getId(),
                    'name' => $muscle->getName(),
                    'bodyPart' => $muscle->getBodyPart() ? $muscle->getBodyPart()->getName() : null,
                    'activationLevel' => $activationLevel?->value,
                    'activationLabel' => $activationLevel?->getLabel(),
                ];
            }
        }

        $supportedGoals = [];
        foreach ($exercise->getSupportedGoals() as $sg) {
            $goalType = $sg->getGoalType();
            if ($goalType) {
                $supportedGoals[] = [
                    'id' => $goalType->getId(),
                    'name' => $goalType->getName(),
                    'label' => $goalType->getLabel(),
                ];
            }
        }

        $data = [
            'id' => $exercise->getId(),
            'name' => $exercise->getName(),
            'difficulty' => $exercise->getDifficulty(),
            'type' => $exercise->getType(),
            'gifUrl' => $exercise->getGifUrl(),
            'description' => $exercise->getDescription(),
            'muscles' => $muscles,
            'supportedGoals' => $supportedGoals,
        ];

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}/last-history', name: 'api_exercises_last_history', methods: ['GET'])]
    public function lastHistory(int $id, WorkoutService $workoutService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $history = $workoutService->getLastExerciseHistory($user, $id);

        return new JsonResponse($history, Response::HTTP_OK);
    }
}
