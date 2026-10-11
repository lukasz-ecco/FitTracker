<?php

namespace App\Service\TrainingPlan;

use App\Entity\TrainingPlan;
use App\Entity\TrainingPlanDay;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Repository\TrainingPlanDayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TrainingPlanSessionLauncher
{
    public function __construct(
        private EntityManagerInterface $em,
        private TrainingPlanDayRepository $planDayRepository
    ) {}

    public function startWorkoutFromPlan(User $user, int $planDayId): Workout
    {
        $planDay = $this->planDayRepository->find($planDayId);
        if (!$planDay) {
            throw new NotFoundHttpException('Nie znaleziono dnia w planie treningowym.');
        }

        $plan = $planDay->getTrainingPlan();
        $this->checkPlanAccess($plan, $user);

        $session = new Workout();
        $session->setUser($user);
        $session->setTrainingPlan($plan);
        $session->setPlanDay($planDay);
        $session->setDayNumber($planDay->getDayNumber());
        $session->setName($planDay->getName());
        $session->setDescription($planDay->getDescription());
        $session->setStatus('IN_PROGRESS');
        $session->setDate(new \DateTime());
        $session->setActivityType($planDay->getActivityType() ?? 'WORKOUT');
        $session->setTemplate($planDay->getTemplate());

        $this->em->persist($session);

        if ($planDay->getExercises()->count() > 0) {
            foreach ($planDay->getExercises() as $pe) {
                $sessionExercise = new WorkoutExercise();
                $sessionExercise->setWorkout($session);
                $sessionExercise->setExercise($pe->getExercise());
                $sessionExercise->setOrderIndex($pe->getOrderIndex());
                $sessionExercise->setNotes($pe->getNotes());

                $this->em->persist($sessionExercise);
                $session->addWorkoutExercise($sessionExercise);

                foreach ($pe->getSets() as $ps) {
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
        } elseif ($planDay->getTemplate()) {
            foreach ($planDay->getTemplate()->getExercises() as $te) {
                $sessionExercise = new WorkoutExercise();
                $sessionExercise->setWorkout($session);
                $sessionExercise->setExercise($te->getExercise());
                $sessionExercise->setOrderIndex($te->getOrderIndex());
                $sessionExercise->setNotes($te->getNotes());

                $this->em->persist($sessionExercise);
                $session->addWorkoutExercise($sessionExercise);

                foreach ($te->getSets() as $ts) {
                    $sessionSet = new WorkoutExerciseSet();
                    $sessionSet->setWorkoutExercise($sessionExercise);
                    $sessionSet->setSetNumber($ts->getSetNumber());
                    $sessionSet->setReps($ts->getReps());
                    $sessionSet->setWeight($ts->getWeight());
                    $sessionSet->setTempo($ts->getTempo());
                    $sessionSet->setIsCompleted(false);

                    $this->em->persist($sessionSet);
                    $sessionExercise->addWorkoutExerciseSet($sessionSet);
                }
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
