<?php

namespace App\Service;

use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Entity\Exercises;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkoutService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator
    ) {}

    public function createWorkout(User $user, array $data): Workout
    {
        if (!isset($data['name'])) {
            throw new \InvalidArgumentException('Brak wymaganych danych (name).');
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
                throw new \InvalidArgumentException('Nieprawidłowy format daty.');
            }
        } else {
            $workout->setDate(new \DateTime());
        }

        $errors = $this->validator->validate($workout);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($workout);
        $this->em->flush();

        return $workout;
    }

    public function updateWorkout(Workout $workout, User $user, array $data): Workout
    {
        if ($workout->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
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
                throw new \InvalidArgumentException('Nieprawidłowy format daty.');
            }
        }

        $errors = $this->validator->validate($workout);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $workout;
    }

    public function deleteWorkout(Workout $workout, User $user): void
    {
        if ($workout->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        $this->em->remove($workout);
        $this->em->flush();
    }

    public function addExerciseToWorkout(Workout $workout, User $user, array $data): WorkoutExercise
    {
        if ($workout->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if (!isset($data['exerciseId'])) {
            throw new \InvalidArgumentException('Brak exerciseId.');
        }

        $exercise = $this->em->getRepository(Exercises::class)->find($data['exerciseId']);
        if (!$exercise) {
            throw new NotFoundHttpException('Nie znaleziono ćwiczenia.');
        }

        $we = new WorkoutExercise();
        $we->setWorkout($workout);
        $we->setExercise($exercise);
        
        $orderIndex = isset($data['orderIndex']) ? (int) $data['orderIndex'] : $workout->getWorkoutExercises()->count() + 1;
        $we->setOrderIndex($orderIndex);

        if (isset($data['notes'])) {
            $we->setNotes($data['notes']);
        }

        $errors = $this->validator->validate($we);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($we);
        $this->em->flush();

        return $we;
    }

    public function addSetToExercise(WorkoutExercise $workoutExercise, User $user, array $data): WorkoutExerciseSet
    {
        if ($workoutExercise->getWorkout()->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if (!isset($data['reps']) || !isset($data['weight'])) {
            throw new \InvalidArgumentException('Brak reps lub weight.');
        }

        $set = new WorkoutExerciseSet();
        $set->setWorkoutExercise($workoutExercise);
        $set->setReps((int) $data['reps']);
        $set->setWeight((float) $data['weight']);
        
        $setNumber = isset($data['setNumber']) ? (int) $data['setNumber'] : $workoutExercise->getWorkoutExerciseSets()->count() + 1;
        $set->setSetNumber($setNumber);

        if (isset($data['tempo'])) $set->setTempo($data['tempo']);
        if (isset($data['isDropSet'])) $set->setDropSet((bool) $data['isDropSet']);

        $errors = $this->validator->validate($set);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($set);
        $this->em->flush();

        return $set;
    }

    public function updateSet(WorkoutExerciseSet $set, User $user, array $data): ?WorkoutExerciseSet
    {
        if ($set->getWorkoutExercise()->getWorkout()->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if (isset($data['reps'])) $set->setReps((int) $data['reps']);
        if (isset($data['weight'])) $set->setWeight((float) $data['weight']);
        if (isset($data['isCompleted'])) $set->setCompleted((bool) $data['isCompleted']);

        if ($set->getReps() === 0 && $set->getWeight() === 0.0) {
            $this->em->remove($set);
            $this->em->flush();
            return null;
        }

        $errors = $this->validator->validate($set);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $set;
    }

    public function cleanupEmptySets(Workout $workout): void
    {
        $hasDeleted = false;
        foreach ($workout->getWorkoutExercises() as $we) {
            foreach ($we->getWorkoutExerciseSets() as $set) {
                if ($set->getReps() === 0 && $set->getWeight() === 0.0) {
                    $this->em->remove($set);
                    $hasDeleted = true;
                }
            }
        }
        
        if ($hasDeleted) {
            $this->em->flush();
        }
    }
}
