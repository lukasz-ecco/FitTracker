<?php

namespace App\Repository;

use App\Entity\WorkoutExerciseSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutExerciseSet>
 */
class WorkoutExerciseSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutExerciseSet::class);
    }

    public function findUserWorkoutExerciseSet(int $id, \App\Entity\User $user): ?WorkoutExerciseSet
    {
        return $this->createQueryBuilder('wes')
            ->join('wes.workoutExercise', 'we')
            ->join('we.workout', 'w')
            ->andWhere('wes.id = :id')
            ->andWhere('w.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
