<?php

namespace App\Repository;

use App\Entity\TrainingPlanDayExerciseSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingPlanDayExerciseSet>
 */
class TrainingPlanDayExerciseSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingPlanDayExerciseSet::class);
    }
}
