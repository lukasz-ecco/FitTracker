<?php

namespace App\Service\TrainingPlan;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TrainingPlanSessionLauncher
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkoutRepository $workoutRepository
    ) {}

    public function startWorkoutFromPlan(User $user, int $planWorkoutId): Workout
    {
        $planWorkout = $this->workoutRepository->find($planWorkoutId);
        if (!$planWorkout || !$planWorkout->getTrainingPlan()) {
            throw new NotFoundHttpException('Nie znaleziono treningu w planie.');
        }

        $plan = $planWorkout->getTrainingPlan();
        $this->checkPlanAccess($plan, $user);

        $session = new Workout();
        $session->setUser($user);
        $session->setTrainingPlan($plan);
        $session->setDayNumber($planWorkout->getDayNumber());
        $session->setName($planWorkout->getName());
        $session->setDescription($planWorkout->getDescription());
        $session->setStatus('IN_PROGRESS');
        $session->setDate(new \DateTime());
        $session->setActivityType($planWorkout->getActivityType() ?? 'WORKOUT');

        $this->em->persist($session);

        foreach ($planWorkout->getWorkoutExercises() as $pe) {
            $sessionExercise = new WorkoutExercise();
            $sessionExercise->setWorkout($session);
            $sessionExercise->setExercise($pe->getExercise());
            $sessionExercise->setOrderIndex($pe->getOrderIndex());
            $sessionExercise->setNotes($pe->getNotes());

            $this->em->persist($sessionExercise);
            $session->addWorkoutExercise($sessionExercise);

            foreach ($pe->getWorkoutExerciseSets() as $ps) {
                $sessionSet = new WorkoutExerciseSet();
                $sessionSet->setWorkoutExercise($sessionExercise);
                $sessionSet->setSetNumber($ps->getSetNumber());
                $sessionSet->setReps($ps->getReps());
                $sessionSet->setWeight($ps->getWeight());
                $sessionSet->setTempo($ps->getTempo());
                $sessionSet->setDropSet($ps->isDropSet());
                $sessionSet->setIsCompleted(false);

                $this->em->persist($sessionSet);
                $sessionExercise->addWorkoutExerciseSet($sessionSet);
            }
        }

        $this->em->flush();

        return $session;
    }

    private function checkPlanAccess(TrainingPlan $plan, User $user): void
    {
        $isOwner = $plan->getUser()->getId() === $user->getId();
        $isCreator = $plan->getCreator() && $plan->getCreator()->getId() === $user->getId();

        if (!$isOwner && !$isCreator) {
            throw new AccessDeniedException('Brak uprawnień do tego planu treningowego.');
        }
    }
}
