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

    /**
     * Zwraca ćwiczenia które mają zewnętrzny URL gifUrl (zaczyna się od 'http')
     * lub mają null gifUrl ale posiadają externalId (można pobrać GIF z API).
     * Używane przez komendę app:import-exercises --gifs-only.
     *
     * @return Exercises[]
     */
    public function findWithExternalGifUrl(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.gifUrl LIKE :http')
            ->orWhere('e.gifUrl IS NULL AND e.externalId IS NOT NULL')
            ->setParameter('http', 'http%')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Wyszukuje ćwiczenia w całej bazie danych:
     * - z opcjonalnym filtrowaniem po fragmencie nazwy (case-insensitive),
     * - posortowane w 1. kolejności malejąco po popularności (liczba użyć w WorkoutExercise),
     * - w 2. kolejności alfabetycznie po nazwie rosnąco,
     * - z paginacją (offset, limit).
     *
     * @param string|null $search
     * @param int $page
     * @param int $limit
     * @return Exercises[]
     */
    public function searchExercises(?string $search = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $qb = $this->createQueryBuilder('e')
            ->leftJoin(\App\Entity\WorkoutExercise::class, 'we', \Doctrine\ORM\Query\Expr\Join::WITH, 'we.exercise = e')
            ->groupBy('e.id');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(e.name) LIKE :search')
               ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        return $qb->addSelect('COUNT(we.id) AS HIDDEN popularity')
            ->orderBy('popularity', 'DESC')
            ->addOrderBy('e.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
