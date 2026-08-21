<?php

namespace App\Service;

use App\Entity\TrainingGoal;
use App\Entity\User;
use App\Entity\GoalType;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TrainingGoalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator
    ) {}

    public function createGoal(User $user, array $data): TrainingGoal
    {
        if (!isset($data['goalTypeId']) || !isset($data['fitnessLevel'])) {
            throw new \InvalidArgumentException('Brak wymaganych danych (goalTypeId, fitnessLevel).');
        }

        $goalType = $this->em->getRepository(GoalType::class)->find($data['goalTypeId']);
        if (!$goalType) {
            throw new NotFoundHttpException('Nie znaleziono typu celu.');
        }

        $existingGoal = $this->em->getRepository(TrainingGoal::class)->findOneBy([
            'user' => $user,
            'goalType' => $goalType,
            'isActive' => true
        ]);
        
        if ($existingGoal) {
            throw new ConflictHttpException('Ten cel już znajduje się na Twojej liście.');
        }

        $goal = new TrainingGoal();
        $goal->setUser($user);
        $goal->setGoalType($goalType);
        $goal->setFitnessLevel((int) $data['fitnessLevel']);
        
        if (isset($data['notes'])) {
            $goal->setNotes($data['notes']);
        }

        $errors = $this->validator->validate($goal);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($goal);
        $this->em->flush();

        return $goal;
    }

    public function updateGoal(TrainingGoal $goal, User $user, array $data): TrainingGoal
    {
        if ($goal->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if (isset($data['goalTypeId'])) {
            $goalType = $this->em->getRepository(GoalType::class)->find($data['goalTypeId']);
            if ($goalType) {
                $goal->setGoalType($goalType);
            }
        }

        if (isset($data['fitnessLevel'])) {
            $goal->setFitnessLevel((int) $data['fitnessLevel']);
        }

        if (array_key_exists('notes', $data)) {
            $goal->setNotes($data['notes'] !== '' ? $data['notes'] : null);
        }

        $errors = $this->validator->validate($goal);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $goal;
    }

    public function deleteGoal(TrainingGoal $goal, User $user): void
    {
        if ($goal->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        $this->em->remove($goal);
        $this->em->flush();
    }
}
