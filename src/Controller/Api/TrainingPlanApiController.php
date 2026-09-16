<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Service\TrainingPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/api/training-plans')]
#[IsGranted('ROLE_USER')]
class TrainingPlanApiController extends AbstractController
{
    #[Route('', name: 'api_training_plans_index', methods: ['GET'])]
    public function index(
        Request $request,
        TrainingPlanRepository $repository,
        UserRepository $userRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $traineeId = $request->query->get('traineeId');
        if ($traineeId && in_array('ROLE_TRAINER', $user->getRoles())) {
            $trainee = $userRepository->find((int) $traineeId);
            if ($trainee) {
                $plans = $repository->findTrainerPlansForTrainee($user, $trainee);
                return $this->json($plans, Response::HTTP_OK, [], ['groups' => ['plan:read', 'plan:read:full', 'workout:read']]);
            }
        }

        $plans = $repository->findUserPlans($user);
        return $this->json($plans, Response::HTTP_OK, [], ['groups' => ['plan:read', 'plan:read:full', 'workout:read']]);
    }

    #[Route('/active', name: 'api_training_plans_active', methods: ['GET'])]
    public function active(TrainingPlanRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findActivePlan($user);
        if (!$plan) {
            return new JsonResponse(null, Response::HTTP_OK);
        }

        return $this->json($plan, Response::HTTP_OK, [], ['groups' => ['plan:read:full', 'workout:read:full', 'exercise:read', 'template:read']]);
    }

    #[Route('/active/recommended-workout', name: 'api_training_plans_recommended_workout', methods: ['GET'])]
    public function recommendedWorkout(TrainingPlanService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $recommendation = $service->getRecommendedWorkout($user);
        if (!$recommendation) {
            return new JsonResponse(['hasActivePlan' => false], Response::HTTP_OK);
        }

        return $this->json($recommendation, Response::HTTP_OK, [], [
            'groups' => ['plan:read', 'workout:read:full', 'exercise:read', 'template:read']
        ]);
    }

    #[Route('/workouts/{workoutId}/start', name: 'api_training_plans_start_workout', requirements: ['workoutId' => '\d+'], methods: ['POST'])]
    public function startWorkout(
        int $workoutId,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $session = $service->startWorkoutFromPlan($user, $workoutId);
        return $this->json($session, Response::HTTP_CREATED, [], [
            'groups' => ['workout:read:full', 'exercise:read', 'plan:read']
        ]);
    }

    #[Route('/{id}', name: 'api_training_plans_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TrainingPlanRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findUserPlan($id, $user);
        if (!$plan) {
            throw new NotFoundHttpException('Nie znaleziono planu treningowego.');
        }

        return $this->json($plan, Response::HTTP_OK, [], ['groups' => ['plan:read:full', 'workout:read:full', 'exercise:read', 'template:read']]);
    }

    #[Route('/{id}/cycle-analysis', name: 'api_training_plans_cycle_analysis', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cycleAnalysis(
        int $id,
        TrainingPlanRepository $repository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findUserPlan($id, $user);
        if (!$plan) {
            throw new NotFoundHttpException('Nie znaleziono planu treningowego.');
        }

        $analysis = $service->getCycleAnalysis($plan);
        return $this->json($analysis, Response::HTTP_OK);
    }

    #[Route('', name: 'api_training_plans_create', methods: ['POST'])]
    public function create(Request $request, TrainingPlanService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $plan = $service->createPlan($user, $data);
        return $this->json($plan, Response::HTTP_CREATED, [], ['groups' => ['plan:read:full', 'workout:read:full']]);
    }

    #[Route('/{id}', name: 'api_training_plans_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function update(
        int $id,
        Request $request,
        TrainingPlanRepository $repository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findUserPlan($id, $user);
        if (!$plan) {
            throw new NotFoundHttpException('Nie znaleziono planu treningowego.');
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $plan = $service->updatePlan($plan, $user, $data);
        return $this->json($plan, Response::HTTP_OK, [], ['groups' => ['plan:read:full', 'workout:read:full']]);
    }

    #[Route('/{id}/activate', name: 'api_training_plans_activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(
        int $id,
        TrainingPlanRepository $repository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findUserPlan($id, $user);
        if (!$plan) {
            throw new NotFoundHttpException('Nie znaleziono planu treningowego.');
        }

        $plan = $service->activatePlan($plan, $user);
        return $this->json($plan, Response::HTTP_OK, [], ['groups' => ['plan:read:full', 'workout:read:full']]);
    }

    #[Route('/{id}', name: 'api_training_plans_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        int $id,
        TrainingPlanRepository $repository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findUserPlan($id, $user);
        if (!$plan) {
            throw new NotFoundHttpException('Nie znaleziono planu treningowego.');
        }

        $service->deletePlan($plan, $user);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/workouts', name: 'api_training_plans_add_workout', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addWorkout(
        int $id,
        Request $request,
        TrainingPlanRepository $repository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $repository->findUserPlan($id, $user);
        if (!$plan) {
            throw new NotFoundHttpException('Nie znaleziono planu treningowego.');
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $workout = $service->addWorkoutToPlan($plan, $user, $data);
        return $this->json($workout, Response::HTTP_CREATED, [], ['groups' => ['workout:read:full', 'plan:read', 'template:read']]);
    }

    #[Route('/workouts/{workoutId}', name: 'api_training_plans_update_workout', requirements: ['workoutId' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function updateWorkout(
        int $workoutId,
        Request $request,
        WorkoutRepository $workoutRepository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $workout = $workoutRepository->find($workoutId);
        if (!$workout) {
            throw new NotFoundHttpException('Nie znaleziono treningu.');
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $updated = $service->updatePlanWorkout($workout, $user, $data);
        return $this->json($updated, Response::HTTP_OK, [], ['groups' => ['workout:read:full', 'plan:read', 'template:read']]);
    }

    #[Route('/workouts/{workoutId}', name: 'api_training_plans_remove_workout', requirements: ['workoutId' => '\d+'], methods: ['DELETE'])]
    public function removeWorkout(
        int $workoutId,
        WorkoutRepository $workoutRepository,
        TrainingPlanService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $workout = $workoutRepository->find($workoutId);
        if (!$workout) {
            throw new NotFoundHttpException('Nie znaleziono treningu.');
        }

        $service->removeWorkoutFromPlan($workout, $user);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
