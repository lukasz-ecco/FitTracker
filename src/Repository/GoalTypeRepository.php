<?php

namespace App\Repository;

use App\Entity\GoalType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoalType>
 */
class GoalTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoalType::class);
    }

    /**
     * Zwraca wszystkie typy celów posortowane alfabetycznie po etykiecie.
     *
     * @return GoalType[]
     */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('gt')
            ->orderBy('gt.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
