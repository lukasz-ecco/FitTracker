<?php

namespace App\Repository;

use App\Entity\TrainingGoal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingGoal>
 */
class TrainingGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingGoal::class);
    }

    /**
     * Znajduje aktywny cel treningowy dla danego użytkownika.
     */
    public function findActiveByUser(User $user): ?TrainingGoal
    {
        return $this->createQueryBuilder('tg')
            ->andWhere('tg.user = :user')
            ->andWhere('tg.isActive = :isActive')
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->orderBy('tg.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Pobiera wszystkie aktywne cele treningowe dla danego użytkownika.
     *
     * @return TrainingGoal[]
     */
    public function findActiveGoalsByUser(User $user): array
    {
        return $this->createQueryBuilder('tg')
            ->andWhere('tg.user = :user')
            ->andWhere('tg.isActive = :isActive')
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->orderBy('tg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pobiera wszystkie cele treningowe użytkownika posortowane od najnowszych.
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('tg')
            ->andWhere('tg.user = :user')
            ->setParameter('user', $user)
            ->orderBy('tg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
