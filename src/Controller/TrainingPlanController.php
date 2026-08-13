<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExerciseSet;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/training-plan')]
#[IsGranted('ROLE_USER')]
class TrainingPlanController extends AbstractController
{
    #[Route('', name: 'app_training_plan', methods: ['GET'])]
    public function index(WorkoutRepository $workoutRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $workouts = $workoutRepository->findBy(['user' => $user], ['date' => 'DESC']);

        return $this->render('training_plan/index.html.twig', [
            'workouts' => $workouts,
        ]);
    }

    #[Route('/{id}', name: 'app_training_plan_show', methods: ['GET'])]
    public function show(Workout $workout): Response
    {
        if ($workout->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Nie masz dostępu do tego treningu.');
        }

        return $this->render('training_plan/show.html.twig', [
            'workout' => $workout,
        ]);
    }

    #[Route('/set/{id}/update', name: 'app_training_plan_set_update', methods: ['POST', 'PATCH'])]
    public function updateSet(Request $request, WorkoutExerciseSet $set, EntityManagerInterface $em): JsonResponse
    {
        if ($set->getWorkoutExercise()->getWorkout()->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['isCompleted'])) {
            $set->setCompleted((bool) $data['isCompleted']);
        }
        if (isset($data['reps'])) {
            $set->setReps((int) $data['reps']);
        }
        if (isset($data['weight'])) {
            $set->setWeight((float) $data['weight']);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
