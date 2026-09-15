<?php

namespace App\Controller\Api;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutExerciseRepository;
use App\Repository\WorkoutExerciseSetRepository;
use App\Service\WorkoutService;
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

#[Route('/api/workouts')]
#[IsGranted('ROLE_USER')]
class WorkoutController extends AbstractController
{
    #[Route('', name: 'api_workouts_get', methods: ['GET'])]
    public function index(Request $request, WorkoutRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $status = $request->query->get('status');
        $criteria = ['user' => $user];
        if ($status) {
            $criteria['status'] = $status;
        }

        $workouts = $repository->findBy($criteria, ['date' => 'DESC', 'name' => 'ASC']);

        return $this->json($workouts, Response::HTTP_OK, [], ['groups' => ['workout:read:full', 'exercise:read', 'template:read']]);
    }

    #[Route('/{id}', name: 'api_workouts_show', methods: ['GET'])]
    public function show(int $id, WorkoutRepository $repository, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $workout = $repository->findUserWorkout($id, $user);
        
        if (!$workout) {
            return new JsonResponse(['error' => 'Brak dostępu lub trening nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $service->cleanupEmptySets($workout);

        return $this->json($workout, Response::HTTP_OK, [], ['groups' => ['workout:read:full']]);
    }

    #[Route('', name: 'api_workouts_create', methods: ['POST'])]
    public function create(Request $request, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }
        
        $workout = $service->createWorkout($user, $data);
        return $this->json($workout, Response::HTTP_CREATED, [], ['groups' => ['workout:read', 'workout:read:full']]);
    }

    #[Route('/{id}', name: 'api_workouts_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, WorkoutRepository $repository, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $workout = $repository->findUserWorkout($id, $user);
        
        if (!$workout) {
            return new JsonResponse(['error' => 'Brak dostępu lub trening nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $workout = $service->updateWorkout($workout, $user, $data);
        return $this->json($workout, Response::HTTP_OK, [], ['groups' => ['workout:read']]);
    }

    #[Route('/{id}', name: 'api_workouts_delete', methods: ['DELETE'])]
    public function delete(int $id, WorkoutRepository $repository, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $workout = $repository->findUserWorkout($id, $user);
        
        if (!$workout) {
            return new JsonResponse(['error' => 'Brak dostępu lub trening nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $service->deleteWorkout($workout, $user);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/exercises', name: 'api_workouts_add_exercise', methods: ['POST'])]
    public function addExercise(int $id, Request $request, WorkoutRepository $repository, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $workout = $repository->findUserWorkout($id, $user);
        
        if (!$workout) {
            return new JsonResponse(['error' => 'Brak dostępu lub trening nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $service->addExerciseToWorkout($workout, $user, $data);
        return $this->json($workout, Response::HTTP_OK, [], ['groups' => ['workout:read:full']]);
    }

    #[Route('/exercises/{id}/sets', name: 'api_workouts_add_set', methods: ['POST'])]
    public function addSet(int $id, Request $request, WorkoutExerciseRepository $repository, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $workoutExercise = $repository->findUserWorkoutExercise($id, $user);
        
        if (!$workoutExercise) {
            return new JsonResponse(['error' => 'Brak dostępu lub ćwiczenie w treningu nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }
        
        $set = $service->addSetToExercise($workoutExercise, $user, $data);
        return $this->json($set, Response::HTTP_CREATED, [], ['groups' => ['set:read']]);
    }

    #[Route('/sets/{id}', name: 'api_workouts_update_set', methods: ['PATCH'])]
    public function updateSet(int $id, Request $request, WorkoutExerciseSetRepository $repository, WorkoutService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $set = $repository->findUserWorkoutExerciseSet($id, $user);
        
        if (!$set) {
            return new JsonResponse(['error' => 'Brak dostępu lub seria nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $set = $service->updateSet($set, $user, $data);
        if (!$set) {
            return new JsonResponse(['status' => 'deleted'], Response::HTTP_OK);
        }
        return $this->json($set, Response::HTTP_OK, [], ['groups' => ['set:read']]);
    }
}
