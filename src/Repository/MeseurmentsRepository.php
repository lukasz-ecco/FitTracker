<?php

namespace App\Repository;

use App\Entity\Meseurments;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meseurments>
 */
class MeseurmentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Meseurments::class);
    }

       /**
        * @return Meseurments[] Returns an array of Meseurments objects
        */
       public function findByUserId($value): array
       {
            $query = $this->createQueryBuilder('m');

            $subQuery = $this->createQueryBuilder('sub')
                ->select('MAX(sub.date)')
                ->where('sub.User = :val')
                ->andWhere('sub.bodyPart = m.bodyPart');

            
           return $query
                ->join('m.bodyPart', 'bp')
                ->addSelect('bp')
                ->where('m.User = :val')
                ->andWhere($query->expr()->eq('m.date', '(' . $subQuery->getDQL() . ')'))
                ->setParameter('val', $value)
                ->orderBy('bp.name', 'ASC')
                ->getQuery()
                ->getResult()
           ;
       }

    public function findUserMeasurement(int $id, \App\Entity\User $user): ?Meseurments
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.User = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
