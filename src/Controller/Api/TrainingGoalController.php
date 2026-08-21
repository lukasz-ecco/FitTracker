<?php

namespace App\Controller\Api;

use App\Entity\GoalType;
use App\Entity\TrainingGoal;
use App\Entity\User;
use App\Repository\TrainingGoalRepository;
use App\Service\ExerciseSuggestionService;
use App\Service\TrainingGoalService;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[Route('/api/training-goals')]
#[IsGranted('ROLE_USER')]
class TrainingGoalController extends AbstractController
{
    #[Route('', name: 'api_training_goals_get', methods: ['GET'])]
    public function index(TrainingGoalRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $goals = $repository->findActiveGoalsByUser($user);

        return $this->json($goals, Response::HTTP_OK, [], ['groups' => ['goal:read']]);
    }

    #[Route('', name: 'api_training_goals_create', methods: ['POST'])]
    public function create(Request $request, TrainingGoalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $service->createGoal($user, $data);
        return $this->json($goal, Response::HTTP_CREATED, [], ['groups' => ['goal:read']]);
    }

    #[Route('/{id}', name: 'api_training_goals_delete', methods: ['DELETE'])]
    public function delete(int $id, TrainingGoalRepository $repository, TrainingGoalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $goal = $repository->findUserGoal($id, $user);
        if (!$goal) {
            return new JsonResponse(['error' => 'Brak dostępu lub cel nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $service->deleteGoal($goal, $user);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'api_training_goals_update', methods: ['PATCH', 'PUT'])]
    public function update(int $id, Request $request, TrainingGoalRepository $repository, TrainingGoalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $goal = $repository->findUserGoal($id, $user);
        if (!$goal) {
            return new JsonResponse(['error' => 'Brak dostępu lub cel nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $service->updateGoal($goal, $user, $data);
        return $this->json($goal, Response::HTTP_OK, [], ['groups' => ['goal:read']]);
    }

    #[Route('/suggestions', name: 'api_training_goals_suggestions', methods: ['GET'], priority: 2)]
    public function suggestions(TrainingGoalRepository $goalRepo, ExerciseSuggestionService $suggestionService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $activeGoals = $goalRepo->findActiveGoalsByUser($user);
        $suggestions = $suggestionService->getSuggestionsForGoals($activeGoals);
        
        return $this->json($suggestions, Response::HTTP_OK, [], ['groups' => ['exercise:read']]);
    }
}
