<?php

namespace App\Controller\Api;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/workouts')]
#[IsGranted('ROLE_USER')]
class WorkoutController extends AbstractController
{
    #[Route('', name: 'api_workouts_get', methods: ['GET'])]
    public function index(WorkoutRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $workouts = $repository->findBy(['user' => $user], ['date' => 'DESC']);

        return $this->json($workouts, Response::HTTP_OK, [], ['groups' => ['workout:read']]);
    }

    #[Route('/{id}', name: 'api_workouts_show', methods: ['GET'])]
    public function show(Workout $workout): JsonResponse
    {
        if ($workout->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($workout, Response::HTTP_OK, [], ['groups' => ['workout:read:full']]);
    }

    #[Route('', name: 'api_workouts_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['name'])) {
            return new JsonResponse(['error' => 'Brak wymaganych danych (name).'], Response::HTTP_BAD_REQUEST);
        }

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setName($data['name']);
        
        if (isset($data['description'])) {
            $workout->setDescription($data['description']);
        }

        if (isset($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
                $workout->setDate($date);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Nieprawidłowy format daty.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $em->persist($workout);
        $em->flush();

        return $this->json($workout, Response::HTTP_CREATED, [], ['groups' => ['workout:read']]);
    }

    #[Route('/{id}', name: 'api_workouts_update', methods: ['PUT', 'PATCH'])]
    public function update(Workout $workout, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($workout->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $workout->setName($data['name']);
        }
        if (isset($data['description'])) {
            $workout->setDescription($data['description']);
        }
        if (isset($data['status'])) {
            $workout->setStatus($data['status']);
        }
        if (isset($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
                $workout->setDate($date);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Nieprawidłowy format daty.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $em->flush();

        return $this->json($workout, Response::HTTP_OK, [], ['groups' => ['workout:read']]);
    }

    #[Route('/{id}', name: 'api_workouts_delete', methods: ['DELETE'])]
    public function delete(Workout $workout, EntityManagerInterface $em): JsonResponse
    {
        if ($workout->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($workout);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/exercises', name: 'api_workouts_add_exercise', methods: ['POST'])]
    public function addExercise(Workout $workout, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($workout->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['exerciseId'])) {
            return new JsonResponse(['error' => 'Brak exerciseId.'], Response::HTTP_BAD_REQUEST);
        }

        $exercise = $em->getRepository(Exercises::class)->find($data['exerciseId']);
        if (!$exercise) {
            return new JsonResponse(['error' => 'Nie znaleziono ćwiczenia.'], Response::HTTP_NOT_FOUND);
        }

        $we = new WorkoutExercise();
        $we->setWorkout($workout);
        $we->setExercise($exercise);
        
        $orderIndex = isset($data['orderIndex']) ? (int) $data['orderIndex'] : $workout->getWorkoutExercises()->count() + 1;
        $we->setOrderIndex($orderIndex);

        if (isset($data['notes'])) {
            $we->setNotes($data['notes']);
        }

        $em->persist($we);
        $em->flush();

        return $this->json($workout, Response::HTTP_OK, [], ['groups' => ['workout:read:full']]);
    }

    #[Route('/exercises/{id}/sets', name: 'api_workouts_add_set', methods: ['POST'])]
    public function addSet(WorkoutExercise $workoutExercise, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($workoutExercise->getWorkout()->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['reps']) || !isset($data['weight'])) {
            return new JsonResponse(['error' => 'Brak reps lub weight.'], Response::HTTP_BAD_REQUEST);
        }

        $set = new WorkoutExerciseSet();
        $set->setWorkoutExercise($workoutExercise);
        $set->setReps((int) $data['reps']);
        $set->setWeight((float) $data['weight']);
        
        $setNumber = isset($data['setNumber']) ? (int) $data['setNumber'] : $workoutExercise->getWorkoutExerciseSets()->count() + 1;
        $set->setSetNumber($setNumber);

        if (isset($data['tempo'])) $set->setTempo($data['tempo']);
        if (isset($data['isDropSet'])) $set->setIsDropSet((bool) $data['isDropSet']);

        $em->persist($set);
        $em->flush();

        return $this->json($set, Response::HTTP_CREATED, [], ['groups' => ['set:read']]);
    }

    #[Route('/sets/{id}', name: 'api_workouts_update_set', methods: ['PATCH'])]
    public function updateSet(WorkoutExerciseSet $set, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($set->getWorkoutExercise()->getWorkout()->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['reps'])) $set->setReps((int) $data['reps']);
        if (isset($data['weight'])) $set->setWeight((float) $data['weight']);
        if (isset($data['isCompleted'])) $set->setIsCompleted((bool) $data['isCompleted']);

        $em->flush();

        return $this->json($set, Response::HTTP_OK, [], ['groups' => ['set:read']]);
    }
}
