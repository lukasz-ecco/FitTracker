<?php

namespace App\Repository;

use App\Entity\TrainerTraineeConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainerTraineeConnection>
 */
class TrainerTraineeConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainerTraineeConnection::class);
    }

    public function findConnection(int $id, \App\Entity\User $user): ?TrainerTraineeConnection
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id = :id')
            ->andWhere('t.trainee = :user OR t.trainer = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
