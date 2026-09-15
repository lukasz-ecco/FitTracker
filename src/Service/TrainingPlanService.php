<?php

namespace App\Service;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Exception\ValidationException;
use App\Repository\TrainingPlanRepository;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutTemplateRepository;
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
        private ?WorkoutTemplateRepository $templateRepository = null
    ) {}

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

            // Sprawdzenie czy twórca ma zaakceptowaną relację trenerską z tym podopiecznym
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

        if (isset($data['description'])) {
            $plan->setDescription($data['description']);
        }

        $isActive = !empty($data['isActive']);
        if ($isActive) {
            $this->deactivateUserPlans($targetUser);
            $plan->setIsActive(true);
        }

        $errors = $this->validator->validate($plan);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($plan);

        // Obsługa wstępnie przesłanych dni/treningów
        if (!empty($data['workouts']) && is_array($data['workouts'])) {
            foreach ($data['workouts'] as $wData) {
                $this->addWorkoutToPlanInternal($plan, $creator, $wData);
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

    public function addWorkoutToPlan(TrainingPlan $plan, User $user, array $data): Workout
    {
        $this->checkPlanAccess($plan, $user);

        $workout = $this->addWorkoutToPlanInternal($plan, $user, $data);
        $this->em->flush();

        return $workout;
    }

    public function removeWorkoutFromPlan(Workout $workout, User $user): void
    {
        $plan = $workout->getTrainingPlan();
        if (!$plan) {
            throw new \InvalidArgumentException('Trening nie jest przypisany do żadnego planu.');
        }

        $this->checkPlanAccess($plan, $user);

        $workout->setTrainingPlan(null);
        $workout->setDayNumber(null);
        $this->em->flush();
    }

    public function updatePlanWorkout(Workout $workout, User $user, array $data): Workout
    {
        $plan = $workout->getTrainingPlan();
        if (!$plan) {
            throw new \InvalidArgumentException('Ten trening nie należy do żadnego planu.');
        }

        $this->checkPlanAccess($plan, $user);

        if (isset($data['name'])) {
            $workout->setName(trim($data['name']));
        }
        if (array_key_exists('description', $data)) {
            $workout->setDescription($data['description'] !== null ? trim($data['description']) : null);
        }

        // Zmiana lub odświeżenie szablonu
        $templateId = $data['templateId'] ?? null;
        $syncWithTemplate = !empty($data['syncWithTemplate']);

        if ($this->templateRepository && ($templateId || ($syncWithTemplate && $workout->getTemplate()))) {
            $template = $templateId ? $this->templateRepository->find((int) $templateId) : $workout->getTemplate();
            if ($template) {
                $workout->setTemplate($template);
                if (empty($data['name'])) {
                    $workout->setName($template->getName());
                }

                // Usunięcie starych ćwiczeń z dnia
                foreach ($workout->getWorkoutExercises() as $we) {
                    $this->em->remove($we);
                }
                $workout->getWorkoutExercises()->clear();
                $this->em->flush();

                // Sklonowanie ćwiczeń i serii z szablonu
                foreach ($template->getExercises() as $te) {
                    $workoutExercise = new WorkoutExercise();
                    $workoutExercise->setExercise($te->getExercise());
                    $workoutExercise->setOrderIndex($te->getOrderIndex());
                    $workoutExercise->setNotes($te->getNotes());

                    foreach ($te->getSets() as $ts) {
                        $set = new WorkoutExerciseSet();
                        $set->setSetNumber($ts->getSetNumber());
                        $set->setReps($ts->getReps());
                        $set->setWeight($ts->getWeight());
                        $set->setTempo($ts->getTempo());
                        $set->setIsCompleted(false);

                        $workoutExercise->addWorkoutExerciseSet($set);
                    }

                    $workout->addWorkoutExercise($workoutExercise);
                }
            }
        }

        $errors = $this->validator->validate($workout);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $workout;
    }

    public function deletePlan(TrainingPlan $plan, User $user): void
    {
        $this->checkPlanAccess($plan, $user);

        foreach ($plan->getWorkouts() as $workout) {
            $workout->setTrainingPlan(null);
            $workout->setDayNumber(null);
        }

        $this->em->remove($plan);
        $this->em->flush();
    }

    private function addWorkoutToPlanInternal(TrainingPlan $plan, User $user, array $data): Workout
    {
        $isRestDay = !empty($data['isRestDay']);
        $dayNumber = isset($data['dayNumber']) ? (int) $data['dayNumber'] : ($plan->getWorkouts()->count() + 1);

        // Opcja 1: Utworzenie dnia na podstawie szablonu (WorkoutTemplate)
        $templateId = $data['templateId'] ?? null;
        if ($templateId) {
            $template = $this->templateRepository->find((int) $templateId);
            if (!$template) {
                throw new \InvalidArgumentException('Podany szablon treningowy nie istnieje.');
            }
            if ($template->getUser()->getId() !== $plan->getUser()->getId() && 
                (!$plan->getCreator() || $template->getUser()->getId() !== $plan->getCreator()->getId())) {
                throw new AccessDeniedException('Nie masz dostępu do tego szablonu.');
            }

            $name = !empty($data['name']) ? $data['name'] : $template->getName();

            $workout = new Workout();
            $workout->setName($name);
            $workout->setUser($plan->getUser());
            if ($plan->getCreator() && $plan->getCreator()->getId() !== $plan->getUser()->getId()) {
                $workout->setTrainer($plan->getCreator());
            }
            $workout->setTrainingPlan($plan);
            $workout->setTemplate($template);
            $workout->setDayNumber($dayNumber);
            $workout->setIsRestDay($isRestDay);
            $workout->setStatus('PLANNED');

            $notes = $data['notes'] ?? $template->getDescription();
            if ($notes) {
                $workout->setDescription($notes);
            }

            // Kopiowanie ćwiczeń i serii z szablonu do nowego rekordu Workout
            foreach ($template->getExercises() as $te) {
                $workoutExercise = new WorkoutExercise();
                $workoutExercise->setExercise($te->getExercise());
                $workoutExercise->setOrderIndex($te->getOrderIndex());
                $workoutExercise->setNotes($te->getNotes());

                foreach ($te->getSets() as $ts) {
                    $set = new WorkoutExerciseSet();
                    $set->setSetNumber($ts->getSetNumber());
                    $set->setReps($ts->getReps());
                    $set->setWeight($ts->getWeight());
                    $set->setTempo($ts->getTempo());
                    $set->setIsCompleted(false);

                    $workoutExercise->addWorkoutExerciseSet($set);
                }

                $workout->addWorkoutExercise($workoutExercise);
            }

            $errors = $this->validator->validate($workout);
            if (count($errors) > 0) {
                throw new ValidationException($errors);
            }

            $this->em->persist($workout);
            $plan->addWorkout($workout);

            return $workout;
        }

        // Opcja 2: Podpięcie istniejącego treningu (kompatybilność wsteczna)
        if (!empty($data['existingWorkoutId'])) {
            $workout = $this->workoutRepository->find($data['existingWorkoutId']);
            if (!$workout) {
                throw new \InvalidArgumentException('Podany trening nie istnieje.');
            }
            if ($workout->getUser()->getId() !== $plan->getUser()->getId()) {
                throw new AccessDeniedException('Nie masz dostępu do tego treningu.');
            }

            $workout->setTrainingPlan($plan);
            $workout->setDayNumber($dayNumber);
            $workout->setIsRestDay($isRestDay);

            if (isset($data['notes'])) {
                $workout->setDescription($data['notes']);
            }
            return $workout;
        }

        // Opcja 2: Utworzenie nowego dnia w planie
        $name = !empty($data['name']) 
            ? $data['name'] 
            : ($isRestDay ? "Dzień $dayNumber - Odpoczynek" : "Dzień $dayNumber - Trening");

        $workout = new Workout();
        $workout->setName($name);
        $workout->setUser($plan->getUser());
        if ($plan->getCreator() && $plan->getCreator()->getId() !== $plan->getUser()->getId()) {
            $workout->setTrainer($plan->getCreator());
        }
        $workout->setTrainingPlan($plan);
        $workout->setDayNumber($dayNumber);
        $workout->setIsRestDay($isRestDay);
        $workout->setStatus('PLANNED');

        $notes = $data['notes'] ?? $data['description'] ?? null;
        if ($notes) {
            $workout->setDescription($notes);
        }

        $errors = $this->validator->validate($workout);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($workout);
        $plan->addWorkout($workout);

        return $workout;
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
}
