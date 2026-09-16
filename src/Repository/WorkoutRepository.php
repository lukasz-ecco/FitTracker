<?php

namespace App\Repository;

use App\Entity\Workout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workout>
 */
class WorkoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workout::class);
    }

    public function findUserWorkout(int $id, \App\Entity\User $user): ?Workout
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.id = :id')
            ->andWhere('w.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Workout[]
     */
    public function findCompletedWorkoutsForPlan(\App\Entity\User $user, \App\Entity\TrainingPlan $plan, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.trainingPlan = :plan')
            ->andWhere('w.status = :status')
            ->setParameter('user', $user)
            ->setParameter('plan', $plan)
            ->setParameter('status', 'COMPLETED')
            ->orderBy('w.date', 'DESC');

        if ($since) {
            $qb->andWhere('w.date >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Workout[]
     */
    public function findTodayCompletedWorkouts(\App\Entity\User $user, ?\DateTimeInterface $date = null): array
    {
        $targetDate = $date ? (clone $date) : new \DateTime();
        $startOfDay = (clone $targetDate)->setTime(0, 0, 0);
        $endOfDay = (clone $targetDate)->setTime(23, 59, 59);

        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.status = :status')
            ->andWhere('w.date >= :startOfDay')
            ->andWhere('w.date <= :endOfDay')
            ->setParameter('user', $user)
            ->setParameter('status', 'COMPLETED')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('w.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
