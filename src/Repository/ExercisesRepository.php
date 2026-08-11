<?php

namespace App\Repository;

use App\Entity\Exercises;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercises>
 */
class ExercisesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercises::class);
    }

    /**
     * Finds all exercises with their associated muscles (avoids N+1 problem).
     *
     * @return Exercises[]
     */
    public function findAllWithMuscles(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.Muscle', 'm')
            ->addSelect('m')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
