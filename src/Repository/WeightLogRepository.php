<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WeightLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightLog>
 */
class WeightLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeightLog::class);
    }

    /**
     * @return WeightLog[]
     */
    public function findUserWeightLogs(User $user, int $limit = 30): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('w.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Zwraca wpisy posortowane chronologicznie do wykresu
     * @return WeightLog[]
     */
    public function findUserWeightHistoryChronological(User $user, int $limit = 14): array
    {
        $logs = $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('w.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($logs);
    }
}
