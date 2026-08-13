<?php

namespace App\Repository;

use App\Entity\Exercises;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\GoalType;

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

    /**
     * Wyszukuje ćwiczenia pasujące do podanych typów oraz o trudności nie większej niż podana.
     * Jeśli tablica typów jest pusta, wyszukuje wszystkie typy ćwiczeń.
     *
     * @param string[] $types
     * @param int $maxDifficulty
     * @return Exercises[]
     */
    public function findByTypesAndDifficulty(array $types, int $maxDifficulty): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.Muscle', 'm')
            ->addSelect('m')
            ->andWhere('e.difficulty <= :maxDifficulty')
            ->setParameter('maxDifficulty', $maxDifficulty);

        if (!empty($types)) {
            $qb->andWhere('e.type IN (:types)')
               ->setParameter('types', $types);
        }

        return $qb->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Wyszukuje ćwiczenia wspierające konkretny cel oraz o trudności nie większej niż podana.
     *
     * @param GoalType $goalType
     * @param int $maxDifficulty
     * @return Exercises[]
     */
    public function findByGoalAndMaxDifficulty(GoalType $goalType, int $maxDifficulty): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.exerciseMuscles', 'em')->addSelect('em')
            ->leftJoin('em.Muscle', 'm')->addSelect('m')
            ->join('e.supportedGoals', 'sg')
            ->andWhere('sg.goalType = :goal')
            ->andWhere('e.difficulty <= :maxDifficulty')
            ->setParameter('goal', $goalType)
            ->setParameter('maxDifficulty', $maxDifficulty)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
