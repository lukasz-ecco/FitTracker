<?php

namespace App\Controller\Api;

use App\Entity\GoalType;
use App\Entity\TrainingGoal;
use App\Entity\User;
use App\Repository\TrainingGoalRepository;
use App\Service\ExerciseSuggestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['goalTypeId']) || !isset($data['fitnessLevel'])) {
            return new JsonResponse(['error' => 'Brak wymaganych danych (goalTypeId, fitnessLevel).'], Response::HTTP_BAD_REQUEST);
        }

        $goalType = $em->getRepository(GoalType::class)->find($data['goalTypeId']);
        if (!$goalType) {
            return new JsonResponse(['error' => 'Nie znaleziono typu celu.'], Response::HTTP_NOT_FOUND);
        }

        $goal = new TrainingGoal();
        $goal->setUser($user);
        $goal->setGoalType($goalType);
        $goal->setFitnessLevel((int) $data['fitnessLevel']);
        
        if (isset($data['notes'])) {
            $goal->setNotes($data['notes']);
        }

        $em->persist($goal);
        $em->flush();

        return $this->json($goal, Response::HTTP_CREATED, [], ['groups' => ['goal:read']]);
    }

    #[Route('/{id}', name: 'api_training_goals_delete', methods: ['DELETE'])]
    public function delete(TrainingGoal $goal, EntityManagerInterface $em): JsonResponse
    {
        if ($goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($goal);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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
