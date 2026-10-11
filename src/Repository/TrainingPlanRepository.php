<?php

namespace App\Repository;

use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingPlan>
 */
class TrainingPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingPlan::class);
    }

    public function findUserPlan(int $id, User $user): ?TrainingPlan
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.days', 'd')->addSelect('d')
            ->leftJoin('d.exercises', 'de')->addSelect('de')
            ->leftJoin('de.exercise', 'e')->addSelect('e')
            ->leftJoin('de.sets', 's')->addSelect('s')
            ->leftJoin('d.template', 't')->addSelect('t')
            ->andWhere('p.id = :id')
            ->andWhere('p.user = :user OR p.creator = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrainingPlan[]
     */
    public function findUserPlans(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.days', 'd')->addSelect('d')
            ->andWhere('p.user = :user OR p.creator = :user')
            ->setParameter('user', $user)
            ->orderBy('p.isActive', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActivePlan(User $user): ?TrainingPlan
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.days', 'd')->addSelect('d')
            ->leftJoin('d.exercises', 'de')->addSelect('de')
            ->leftJoin('de.exercise', 'e')->addSelect('e')
            ->leftJoin('de.sets', 's')->addSelect('s')
            ->leftJoin('d.template', 't')->addSelect('t')
            ->andWhere('p.user = :user')
            ->andWhere('p.isActive = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrainingPlan[]
     */
    public function findTrainerPlansForTrainee(User $trainer, User $trainee): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.days', 'd')->addSelect('d')
            ->andWhere('p.creator = :trainer')
            ->andWhere('p.user = :trainee')
            ->setParameter('trainer', $trainer)
            ->setParameter('trainee', $trainee)
            ->orderBy('p.isActive', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
