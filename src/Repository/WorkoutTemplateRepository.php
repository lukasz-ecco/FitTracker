<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkoutTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutTemplate>
 */
class WorkoutTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutTemplate::class);
    }

    /**
     * @return WorkoutTemplate[]
     */
    public function findUserTemplates(User $user): array
    {
        return $this->createQueryBuilder('wt')
            ->leftJoin('wt.exercises', 'wte')
            ->leftJoin('wte.exercise', 'e')
            ->addSelect('wte', 'e')
            ->andWhere('wt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('wt.createdAt', 'DESC')
            ->addOrderBy('wt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUserTemplate(int $id, User $user): ?WorkoutTemplate
    {
        return $this->createQueryBuilder('wt')
            ->leftJoin('wt.exercises', 'wte')
            ->leftJoin('wte.exercise', 'e')
            ->leftJoin('wte.sets', 'wtes')
            ->addSelect('wte', 'e', 'wtes')
            ->andWhere('wt.id = :id')
            ->andWhere('wt.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
