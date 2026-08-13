<?php

namespace App\Repository;

use App\Entity\ExerciseSupportedGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseSupportedGoal>
 */
class ExerciseSupportedGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseSupportedGoal::class);
    }
}
