<?php

namespace App\Repository;

use App\Entity\WorkoutTemplateExerciseSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutTemplateExerciseSet>
 */
class WorkoutTemplateExerciseSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutTemplateExerciseSet::class);
    }
}
