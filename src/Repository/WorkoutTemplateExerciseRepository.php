<?php

namespace App\Repository;

use App\Entity\WorkoutTemplateExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutTemplateExercise>
 */
class WorkoutTemplateExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutTemplateExercise::class);
    }
}
