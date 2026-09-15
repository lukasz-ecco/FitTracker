<?php

namespace App\Service;

use App\Entity\Exercises;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutTemplate;
use App\Entity\WorkoutTemplateExercise;
use App\Entity\WorkoutTemplateExerciseSet;
use App\Exception\ValidationException;
use App\Repository\ExercisesRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WorkoutTemplateService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private WorkoutTemplateRepository $templateRepository,
        private WorkoutRepository $workoutRepository,
        private ExercisesRepository $exercisesRepository
    ) {}

    /**
     * @return WorkoutTemplate[]
     */
    public function getUserTemplates(User $user): array
    {
        return $this->templateRepository->findUserTemplates($user);
    }

    public function getTemplate(int $id, User $user): WorkoutTemplate
    {
        $template = $this->templateRepository->findUserTemplate($id, $user);
        if (!$template) {
            throw new NotFoundHttpException('Szablon treningowy nie istnieje lub brak do niego dostępu.');
        }

        return $template;
    }

    public function createTemplate(User $user, array $data): WorkoutTemplate
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nazwa szablonu jest wymagana.');
        }

        $template = new WorkoutTemplate();
        $template->setName(trim($data['name']));
        $template->setUser($user);

        if (!empty($data['description'])) {
            $template->setDescription(trim($data['description']));
        }

        if (!empty($data['exercises']) && is_array($data['exercises'])) {
            $order = 0;
            foreach ($data['exercises'] as $exData) {
                if (empty($exData['exerciseId'])) {
                    continue;
                }

                $exercise = $this->exercisesRepository->find((int) $exData['exerciseId']);
                if (!$exercise) {
                    continue;
                }

                $templateExercise = new WorkoutTemplateExercise();
                $templateExercise->setExercise($exercise);
                $templateExercise->setOrderIndex(isset($exData['orderIndex']) ? (int) $exData['orderIndex'] : $order++);
                if (!empty($exData['notes'])) {
                    $templateExercise->setNotes($exData['notes']);
                }

                if (!empty($exData['sets']) && is_array($exData['sets'])) {
                    $setNumber = 1;
                    foreach ($exData['sets'] as $setData) {
                        $templateSet = new WorkoutTemplateExerciseSet();
                        $templateSet->setSetNumber(isset($setData['setNumber']) ? (int) $setData['setNumber'] : $setNumber++);
                        if (isset($setData['reps'])) {
                            $templateSet->setReps((int) $setData['reps']);
                        }
                        if (isset($setData['weight'])) {
                            $templateSet->setWeight((float) $setData['weight']);
                        }
                        if (!empty($setData['tempo'])) {
                            $templateSet->setTempo($setData['tempo']);
                        }

                        $templateExercise->addSet($templateSet);
                    }
                }

                $template->addExercise($templateExercise);
            }
        }

        $errors = $this->validator->validate($template);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($template);
        $this->em->flush();

        return $template;
    }

    public function createTemplateFromWorkout(User $user, int $workoutId, ?string $customName = null): WorkoutTemplate
    {
        $workout = $this->workoutRepository->findUserWorkout($workoutId, $user);
        if (!$workout) {
            throw new NotFoundHttpException('Trening nie istnieje lub brak do niego dostępu.');
        }

        $template = new WorkoutTemplate();
        $name = !empty($customName) ? trim($customName) : ($workout->getName() . ' (Szablon)');
        $template->setName($name);
        $template->setUser($user);
        $template->setDescription($workout->getDescription());

        // Kopiowanie ćwiczeń i serii z historycznego treningu
        $order = 0;
        foreach ($workout->getWorkoutExercises() as $we) {
            $templateExercise = new WorkoutTemplateExercise();
            $templateExercise->setExercise($we->getExercise());
            $templateExercise->setOrderIndex($we->getOrderIndex() ?? $order++);
            $templateExercise->setNotes($we->getNotes());

            $setNumber = 1;
            foreach ($we->getWorkoutExerciseSets() as $set) {
                $templateSet = new WorkoutTemplateExerciseSet();
                $templateSet->setSetNumber($set->getSetNumber() ?? $setNumber++);
                $templateSet->setReps($set->getReps());
                $templateSet->setWeight($set->getWeight());
                $templateSet->setTempo($set->getTempo());

                $templateExercise->addSet($templateSet);
            }

            $template->addExercise($templateExercise);
        }

        $errors = $this->validator->validate($template);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($template);
        $this->em->flush();

        return $template;
    }

    public function updateTemplate(WorkoutTemplate $template, User $user, array $data): WorkoutTemplate
    {
        $this->checkAccess($template, $user);

        if (isset($data['name'])) {
            if (empty(trim($data['name']))) {
                throw new \InvalidArgumentException('Nazwa szablonu nie może być pusta.');
            }
            $template->setName(trim($data['name']));
        }

        if (array_key_exists('description', $data)) {
            $template->setDescription($data['description'] !== null ? trim($data['description']) : null);
        }

        if (isset($data['exercises']) && is_array($data['exercises'])) {
            foreach ($template->getExercises() as $existing) {
                $this->em->remove($existing);
            }
            $template->getExercises()->clear();
            $this->em->flush();

            $order = 0;
            foreach ($data['exercises'] as $exData) {
                if (empty($exData['exerciseId'])) {
                    continue;
                }

                $exercise = $this->exercisesRepository->find((int) $exData['exerciseId']);
                if (!$exercise) {
                    continue;
                }

                $templateExercise = new WorkoutTemplateExercise();
                $templateExercise->setExercise($exercise);
                $templateExercise->setOrderIndex(isset($exData['orderIndex']) ? (int) $exData['orderIndex'] : $order++);
                if (!empty($exData['notes'])) {
                    $templateExercise->setNotes($exData['notes']);
                }

                if (!empty($exData['sets']) && is_array($exData['sets'])) {
                    $setNumber = 1;
                    foreach ($exData['sets'] as $setData) {
                        $templateSet = new WorkoutTemplateExerciseSet();
                        $templateSet->setSetNumber(isset($setData['setNumber']) ? (int) $setData['setNumber'] : $setNumber++);
                        if (isset($setData['reps'])) {
                            $templateSet->setReps((int) $setData['reps']);
                        }
                        if (isset($setData['weight'])) {
                            $templateSet->setWeight((float) $setData['weight']);
                        }
                        if (!empty($setData['tempo'])) {
                            $templateSet->setTempo($setData['tempo']);
                        }

                        $templateExercise->addSet($templateSet);
                    }
                }

                $template->addExercise($templateExercise);
            }
        }

        $errors = $this->validator->validate($template);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $template;
    }

    public function deleteTemplate(WorkoutTemplate $template, User $user): void
    {
        $this->checkAccess($template, $user);

        $this->em->remove($template);
        $this->em->flush();
    }

    private function checkAccess(WorkoutTemplate $template, User $user): void
    {
        if ($template->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu do tego szablonu.');
        }
    }
}
