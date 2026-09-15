<?php

namespace App\Repository;

use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingPlan>
 */
class TrainingPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingPlan::class);
    }

    public function findUserPlan(int $id, User $user): ?TrainingPlan
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.workouts', 'w')->addSelect('w')
            ->leftJoin('w.workoutExercises', 'we')->addSelect('we')
            ->leftJoin('we.exercise', 'e')->addSelect('e')
            ->leftJoin('we.workoutExerciseSets', 's')->addSelect('s')
            ->andWhere('p.id = :id')
            ->andWhere('p.user = :user OR p.creator = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrainingPlan[]
     */
    public function findUserPlans(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.workouts', 'w')->addSelect('w')
            ->andWhere('p.user = :user OR p.creator = :user')
            ->setParameter('user', $user)
            ->orderBy('p.isActive', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActivePlan(User $user): ?TrainingPlan
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.workouts', 'w')->addSelect('w')
            ->leftJoin('w.workoutExercises', 'we')->addSelect('we')
            ->leftJoin('we.exercise', 'e')->addSelect('e')
            ->leftJoin('we.workoutExerciseSets', 's')->addSelect('s')
            ->andWhere('p.user = :user')
            ->andWhere('p.isActive = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrainingPlan[]
     */
    public function findTrainerPlansForTrainee(User $trainer, User $trainee): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.workouts', 'w')->addSelect('w')
            ->andWhere('p.creator = :trainer')
            ->andWhere('p.user = :trainee')
            ->setParameter('trainer', $trainer)
            ->setParameter('trainee', $trainee)
            ->orderBy('p.isActive', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
