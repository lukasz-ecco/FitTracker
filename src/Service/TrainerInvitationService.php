<?php

namespace App\Service;

use App\Entity\TrainerTraineeConnection;
use App\Entity\User;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class TrainerInvitationService
{
    public function __construct(
        private UserRepository $userRepo,
        private TrainerTraineeConnectionRepository $connectionRepo,
        private EntityManagerInterface $em
    ) {
    }

    public function invite(User $trainer, ?string $email): void
    {
        if (!$email) {
            throw new InvalidArgumentException('Podaj adres e-mail.');
        }

        $trainee = $this->userRepo->findOneBy(['email' => $email]);
        
        if (!$trainee) {
            throw new InvalidArgumentException('Nie znaleziono użytkownika o podanym adresie e-mail.');
        }

        if (!in_array('ROLE_TRAINEE', $trainee->getRoles())) {
            throw new InvalidArgumentException('Ten użytkownik nie posiada konta typu Podopieczny.');
        }

        if ($trainee === $trainer) {
            throw new InvalidArgumentException('Nie możesz zaprosić samego siebie.');
        }

        $existingConnection = $this->connectionRepo->findOneBy([
            'trainer' => $trainer,
            'trainee' => $trainee
        ]);

        if ($existingConnection) {
            throw new InvalidArgumentException('Ten podopieczny został już przez Ciebie zaproszony.');
        }

        $connection = new TrainerTraineeConnection();
        $connection->setTrainer($trainer);
        $connection->setTrainee($trainee);
        $connection->setStatus('PENDING');

        $this->em->persist($connection);
        $this->em->flush();
    }
}
