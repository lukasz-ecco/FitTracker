<?php

namespace App\Service;

use App\Entity\TrainerTraineeConnection;
use App\Entity\User;
use App\Repository\TrainerTraineeConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TrainerTraineeConnectionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TrainerTraineeConnectionRepository $repository
    ) {}

    public function acceptConnection(TrainerTraineeConnection $connection, User $user): void
    {
        if ($connection->getTrainee()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if ($connection->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Można zaakceptować tylko oczekujące zaproszenia.');
        }

        $connection->setStatus('ACCEPTED');
        $this->em->flush();
    }

    public function rejectConnection(TrainerTraineeConnection $connection, User $user): void
    {
        if ($connection->getTrainee()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if ($connection->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Można odrzucić tylko oczekujące zaproszenia.');
        }

        $connection->setStatus('REJECTED');
        $this->em->flush();
    }

    public function setMainConnection(TrainerTraineeConnection $connection, User $user): void
    {
        if ($connection->getTrainee()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        if ($connection->getStatus() !== 'ACCEPTED') {
            throw new \InvalidArgumentException('Trener musi być zaakceptowany.');
        }

        // Reset all other connections to isMain = false
        $allUserConnections = $this->repository->findBy(['trainee' => $user, 'isMain' => true]);
        foreach ($allUserConnections as $c) {
            $c->setIsMain(false);
        }

        $connection->setIsMain(true);
        $this->em->flush();
    }
}
