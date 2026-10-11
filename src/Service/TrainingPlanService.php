<?php

namespace App\Service;

use App\Entity\TrainingPlan;
use App\Entity\TrainingPlanDay;
use App\Entity\TrainingPlanDayExercise;
use App\Entity\TrainingPlanDayExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Exception\ValidationException;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Repository\TrainingPlanDayRepository;
use App\Repository\TrainingPlanRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutTemplateRepository;
use App\Service\TrainingPlan\TrainingPlanCycleAnalyzer;
use App\Service\TrainingPlan\TrainingPlanRecommendationService;
use App\Service\TrainingPlan\TrainingPlanSessionLauncher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TrainingPlanService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private TrainerTraineeConnectionRepository $connectionRepository,
        private UserRepository $userRepository,
        private WorkoutRepository $workoutRepository,
        private TrainingPlanRepository $planRepository,
        private TrainingPlanDayRepository $planDayRepository,
        private ?WorkoutTemplateRepository $templateRepository = null,
        private ?TrainingPlanCycleAnalyzer $cycleAnalyzer = null,
        private ?TrainingPlanRecommendationService $recommendationService = null,
        private ?TrainingPlanSessionLauncher $sessionLauncher = null
    ) {
        $this->cycleAnalyzer ??= new TrainingPlanCycleAnalyzer();
        $this->recommendationService ??= new TrainingPlanRecommendationService($this->planRepository, $this->workoutRepository);
        $this->sessionLauncher ??= new TrainingPlanSessionLauncher($this->em, $this->planDayRepository);
    }

    public function createPlan(User $creator, array $data): TrainingPlan
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nazwa planu jest wymagana.');
        }

        $targetUser = $creator;
        if (!empty($data['traineeId'])) {
            $trainee = $this->userRepository->find($data['traineeId']);
            if (!$trainee) {
                throw new \InvalidArgumentException('Podopieczny o podanym ID nie istnieje.');
            }

            $connection = $this->connectionRepository->findAcceptedConnection($creator, $trainee);
            if (!$connection) {
                throw new AccessDeniedException('Nie możesz utworzyć planu dla użytkownika, który nie jest Twoim zaakceptowanym podopiecznym.');
            }

            $targetUser = $trainee;
        }

        $plan = new TrainingPlan();
        $plan->setName($data['name']);
        $plan->setUser($targetUser);
        $plan->setCreator($creator);

        $inputDays = $data['days'] ?? [];
        $cycleDays = isset($data['cycleDays']) ? max(1, (int) $data['cycleDays']) : (
            !empty($inputDays) ? max(1, ...array_map(fn($d) => (int) ($d['dayNumber'] ?? 1), $inputDays)) : 7
        );
        $plan->setCycleDays($cycleDays);

        if (isset($data['description'])) {
            $plan->setDescription($data['description']);
        }

        $isActive = !empty($data['isActive']);
        if ($isActive) {
            $this->deactivateUserPlans($targetUser);
            $plan->setIsActive(true);
        }

        // Walidacja wypełnienia wszystkich dni cyklu
        if (isset($data['cycleDays']) && !empty($inputDays) && is_array($inputDays)) {
            $coveredDays = [];
            foreach ($inputDays as $dData) {
                if (isset($dData['dayNumber'])) {
                    $coveredDays[(int) $dData['dayNumber']] = true;
                }
            }

            $missingDays = [];
            for ($d = 1; $d <= $cycleDays; $d++) {
                if (!isset($coveredDays[$d])) {
                    $missingDays[] = $d;
                }
            }

            if (!empty($missingDays)) {
                throw new \InvalidArgumentException(sprintf(
                    'Wszystkie %d dni cyklu muszą zostać zdefiniowane. Brakujące dni: %s. Zdefiniuj trening, aktywność lub oznacz jako dzień odpoczynku.',
                    $cycleDays,
                    implode(', ', $missingDays)
                ));
            }
        }

        $errors = $this->validator->validate($plan);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($plan);

        // Dodanie dni planu
        if (!empty($inputDays) && is_array($inputDays)) {
            foreach ($inputDays as $dData) {
                $this->addDayToPlanInternal($plan, $creator, $dData);
            }
        }

        $this->em->flush();

        return $plan;
    }

    public function updatePlan(TrainingPlan $plan, User $user, array $data): TrainingPlan
    {
        $this->checkPlanAccess($plan, $user);

        if (isset($data['name'])) {
            if (empty(trim($data['name']))) {
                throw new \InvalidArgumentException('Nazwa planu nie może być pusta.');
            }
            $plan->setName($data['name']);
        }

        if (isset($data['cycleDays'])) {
            $plan->setCycleDays(max(1, (int) $data['cycleDays']));
        }

        if (array_key_exists('description', $data)) {
            $plan->setDescription($data['description']);
        }

        if (isset($data['isActive'])) {
            if ($data['isActive']) {
                $this->deactivateUserPlans($plan->getUser());
                $plan->setIsActive(true);
            } else {
                $plan->setIsActive(false);
            }
        }

        $errors = $this->validator->validate($plan);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $plan;
    }

    public function activatePlan(TrainingPlan $plan, User $user): TrainingPlan
    {
        $this->checkPlanAccess($plan, $user);

        $this->deactivateUserPlans($plan->getUser());
        $plan->setIsActive(true);

        $this->em->flush();

        return $plan;
    }

    public function addDayToPlan(TrainingPlan $plan, User $user, array $data): TrainingPlanDay
    {
        $this->checkPlanAccess($plan, $user);

        $day = $this->addDayToPlanInternal($plan, $user, $data);
        $this->em->flush();

        return $day;
    }

    public function removeDayFromPlan(TrainingPlanDay $day, User $user): void
    {
        $plan = $day->getTrainingPlan();
        if (!$plan) {
            throw new \InvalidArgumentException('Dzień nie jest przypisany do żadnego planu.');
        }

        $this->checkPlanAccess($plan, $user);

        $plan->removeDay($day);
        $this->em->remove($day);
        $this->em->flush();
    }

    public function updatePlanDay(TrainingPlanDay $day, User $user, array $data): TrainingPlanDay
    {
        $plan = $day->getTrainingPlan();
        if (!$plan) {
            throw new \InvalidArgumentException('Ten dzień nie należy do żadnego planu.');
        }

        $this->checkPlanAccess($plan, $user);

        if (isset($data['name'])) {
            $day->setName(trim($data['name']));
        }
        if (array_key_exists('description', $data)) {
            $day->setDescription($data['description'] !== null ? trim($data['description']) : null);
        }

        $templateId = $data['templateId'] ?? null;
        $syncWithTemplate = !empty($data['syncWithTemplate']);

        if ($this->templateRepository && ($templateId || ($syncWithTemplate && $day->getTemplate()))) {
            $template = $templateId ? $this->templateRepository->find((int) $templateId) : $day->getTemplate();
            if ($template) {
                $day->setTemplate($template);
                if (empty($data['name'])) {
                    $day->setName($template->getName());
                }

                foreach ($day->getExercises() as $de) {
                    $this->em->remove($de);
                }
                $day->getExercises()->clear();
                $this->em->flush();

                foreach ($template->getExercises() as $te) {
                    $dayExercise = new TrainingPlanDayExercise();
                    $dayExercise->setExercise($te->getExercise());
                    $dayExercise->setOrderIndex($te->getOrderIndex());
                    $dayExercise->setNotes($te->getNotes());

                    foreach ($te->getSets() as $ts) {
                        $set = new TrainingPlanDayExerciseSet();
                        $set->setSetNumber($ts->getSetNumber());
                        $set->setReps($ts->getReps());
                        $set->setWeight($ts->getWeight());
                        $set->setTempo($ts->getTempo());
                        $set->setIsDropSet($ts->isDropSet());

                        $dayExercise->addSet($set);
                    }

                    $day->addExercise($dayExercise);
                }
            }
        }

        if (array_key_exists('isRestDay', $data)) {
            $day->setIsRestDay((bool) $data['isRestDay']);
        }

        if (array_key_exists('activityType', $data)) {
            $day->setActivityType($data['activityType']);
            if ($data['activityType'] && $data['activityType'] !== 'WORKOUT') {
                $day->setIsRestDay(true);
            }
        }

        if (array_key_exists('plannedDurationMinutes', $data)) {
            $day->setPlannedDurationMinutes($data['plannedDurationMinutes'] !== null ? (int) $data['plannedDurationMinutes'] : null);
        }

        if (array_key_exists('plannedDistanceKm', $data)) {
            $day->setPlannedDistanceKm($data['plannedDistanceKm'] !== null ? (float) $data['plannedDistanceKm'] : null);
        }

        if (isset($data['dayNumber'])) {
            $day->setDayNumber((int) $data['dayNumber']);
        }

        $errors = $this->validator->validate($day);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $day;
    }

    public function deletePlan(TrainingPlan $plan, User $user): void
    {
        $this->checkPlanAccess($plan, $user);

        foreach ($plan->getExecutedWorkouts() as $workout) {
            $workout->setTrainingPlan(null);
            $workout->setPlanDay(null);
        }

        $this->em->remove($plan);
        $this->em->flush();
    }

    private function addDayToPlanInternal(TrainingPlan $plan, User $user, array $data): TrainingPlanDay
    {
        $activityType = $data['activityType'] ?? null;
        $isRestDay = !empty($data['isRestDay']);

        if ($activityType === 'FULL_REST') {
            $isRestDay = true;
        } elseif ($activityType && $activityType !== 'WORKOUT') {
            $isRestDay = true;
        } elseif ($isRestDay && empty($activityType)) {
            $activityType = 'FULL_REST';
        } elseif (!$isRestDay && empty($activityType)) {
            $activityType = 'WORKOUT';
        }

        $dayNumber = isset($data['dayNumber']) ? (int) $data['dayNumber'] : ($plan->getDays()->count() + 1);

        $day = new TrainingPlanDay();
        $day->setTrainingPlan($plan);
        $day->setDayNumber($dayNumber);
        $day->setIsRestDay($isRestDay);
        $day->setActivityType($activityType);

        if (isset($data['plannedDurationMinutes'])) {
            $day->setPlannedDurationMinutes((int) $data['plannedDurationMinutes']);
        }
        if (isset($data['plannedDistanceKm'])) {
            $day->setPlannedDistanceKm((float) $data['plannedDistanceKm']);
        }

        $templateId = $data['templateId'] ?? null;
        if ($templateId && $this->templateRepository) {
            $template = $this->templateRepository->find((int) $templateId);
            if (!$template) {
                throw new \InvalidArgumentException('Podany szablon treningowy nie istnieje.');
            }
            if ($template->getUser()->getId() !== $plan->getUser()->getId() && 
                (!$plan->getCreator() || $template->getUser()->getId() !== $plan->getCreator()->getId())) {
                throw new AccessDeniedException('Nie masz dostępu do tego szablonu.');
            }

            $name = !empty($data['name']) ? $data['name'] : $template->getName();
            $day->setName($name);
            $day->setTemplate($template);

            $notes = $data['notes'] ?? $data['description'] ?? $template->getDescription();
            if ($notes) {
                $day->setDescription($notes);
            }

            foreach ($template->getExercises() as $te) {
                $dayExercise = new TrainingPlanDayExercise();
                $dayExercise->setExercise($te->getExercise());
                $dayExercise->setOrderIndex($te->getOrderIndex());
                $dayExercise->setNotes($te->getNotes());

                foreach ($te->getSets() as $ts) {
                    $set = new TrainingPlanDayExerciseSet();
                    $set->setSetNumber($ts->getSetNumber());
                    $set->setReps($ts->getReps());
                    $set->setWeight($ts->getWeight());
                    $set->setTempo($ts->getTempo());
                    $set->setIsDropSet($ts->isDropSet());

                    $dayExercise->addSet($set);
                }

                $day->addExercise($dayExercise);
            }

            $errors = $this->validator->validate($day);
            if (count($errors) > 0) {
                throw new ValidationException($errors);
            }

            $this->em->persist($day);
            $plan->addDay($day);

            return $day;
        }

        $name = !empty($data['name']) ? $data['name'] : $this->getDefaultDayName($dayNumber, $activityType, $isRestDay);
        $day->setName($name);

        $notes = $data['notes'] ?? $data['description'] ?? null;
        if ($notes) {
            $day->setDescription($notes);
        }

        $errors = $this->validator->validate($day);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($day);
        $plan->addDay($day);

        return $day;
    }

    private function getDefaultDayName(int $dayNumber, ?string $activityType, bool $isRestDay): string
    {
        return match ($activityType) {
            'FULL_REST' => "Dzień $dayNumber - Odpoczynek",
            'WALK' => "Dzień $dayNumber - Spacer",
            'CYCLING' => "Dzień $dayNumber - Rower",
            'RUNNING' => "Dzień $dayNumber - Bieganie",
            'SWIMMING' => "Dzień $dayNumber - Pływanie",
            'YOGA' => "Dzień $dayNumber - Joga",
            'STRETCHING' => "Dzień $dayNumber - Rozciąganie",
            default => $isRestDay ? "Dzień $dayNumber - Odpoczynek" : "Dzień $dayNumber - Trening",
        };
    }

    private function deactivateUserPlans(User $user): void
    {
        $activePlans = $this->planRepository->findBy(['user' => $user, 'isActive' => true]);
        foreach ($activePlans as $p) {
            $p->setIsActive(false);
        }
    }

    private function checkPlanAccess(TrainingPlan $plan, User $user): void
    {
        $isOwner = $plan->getUser()->getId() === $user->getId();
        $isCreator = $plan->getCreator() && $plan->getCreator()->getId() === $user->getId();

        if (!$isOwner && !$isCreator) {
            throw new AccessDeniedException('Brak uprawnień do tego planu treningowego.');
        }
    }

    public function getCycleAnalysis(TrainingPlan $plan): array
    {
        return $this->cycleAnalyzer->analyzeCycle($plan);
    }

    public function getRecommendedWorkout(User $user, ?\DateTimeInterface $now = null): ?array
    {
        return $this->recommendationService->getRecommendedWorkout($user, $now);
    }

    public function startWorkoutFromPlan(User $user, int $planDayId): Workout
    {
        return $this->sessionLauncher->startWorkoutFromPlan($user, $planDayId);
    }
}
