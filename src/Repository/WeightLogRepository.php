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

    /**
     * Wyszukuje wpis wagi użytkownika z danego dnia
     */
    public function findOneByUserAndDate(User $user, \DateTimeInterface $date): ?WeightLog
    {
        $startOfDay = (new \DateTimeImmutable($date->format('Y-m-d')))->setTime(0, 0, 0);
        $endOfDay = (new \DateTimeImmutable($date->format('Y-m-d')))->setTime(23, 59, 59);

        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.date >= :start')
            ->andWhere('w.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('w.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
