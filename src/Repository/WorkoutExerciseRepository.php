<?php

namespace App\Repository;

use App\Entity\WorkoutExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutExercise>
 */
class WorkoutExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutExercise::class);
    }

    public function findUserWorkoutExercise(int $id, \App\Entity\User $user): ?WorkoutExercise
    {
        return $this->createQueryBuilder('we')
            ->join('we.workout', 'w')
            ->andWhere('we.id = :id')
            ->andWhere('w.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastCompletedWorkoutExerciseForUser(\App\Entity\User $user, \App\Entity\Exercises $exercise): ?WorkoutExercise
    {
        return $this->createQueryBuilder('we')
            ->join('we.workout', 'w')
            ->andWhere('w.user = :user')
            ->andWhere('we.exercise = :exercise')
            ->andWhere('w.status = :status')
            ->andWhere('SIZE(we.workoutExerciseSets) > 0')
            ->setParameter('user', $user)
            ->setParameter('exercise', $exercise)
            ->setParameter('status', 'COMPLETED')
            ->orderBy('w.date', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
