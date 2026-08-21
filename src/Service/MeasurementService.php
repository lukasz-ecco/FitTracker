<?php

namespace App\Service;

use App\Entity\Meseurments;
use App\Entity\User;
use App\Entity\BodyParts;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MeasurementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator
    ) {}

    public function createMeasurement(User $user, array $data): Meseurments
    {
        if (!isset($data['bodyPartId']) || !isset($data['size'])) {
            throw new \InvalidArgumentException('Brak wymaganych danych (bodyPartId, size).');
        }

        $bodyPart = $this->em->getRepository(BodyParts::class)->find($data['bodyPartId']);
        if (!$bodyPart) {
            throw new NotFoundHttpException('Nie znaleziono partii ciała.');
        }

        $measurement = new Meseurments();
        $measurement->setUser($user);
        $measurement->setBodyPart($bodyPart);
        $measurement->setSize((float) $data['size']);

        if (isset($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
                $measurement->setDate($date);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Nieprawidłowy format daty.');
            }
        } else {
            $measurement->setDate(new \DateTime());
        }

        $errors = $this->validator->validate($measurement);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($measurement);
        $this->em->flush();

        return $measurement;
    }

    public function updateMeasurement(Meseurments $measurement, User $user, array $data): Meseurments
    {
        if ($measurement->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        $now = new \DateTime();
        $measurementDate = $measurement->getDate();
        if ($measurementDate !== null && $now->diff($measurementDate)->days > 7) {
            throw new \InvalidArgumentException('Nie można edytować pomiarów starszych niż 7 dni.');
        }

        if (isset($data['size'])) {
            $measurement->setSize((float) $data['size']);
        }
        
        if (isset($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
                $measurement->setDate($date);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Nieprawidłowy format daty.');
            }
        }
        
        if (isset($data['bodyPartId'])) {
            $bodyPart = $this->em->getRepository(BodyParts::class)->find($data['bodyPartId']);
            if ($bodyPart) {
                $measurement->setBodyPart($bodyPart);
            } else {
                throw new NotFoundHttpException('Nie znaleziono partii ciała.');
            }
        }

        $errors = $this->validator->validate($measurement);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->flush();

        return $measurement;
    }

    public function deleteMeasurement(Meseurments $measurement, User $user): void
    {
        if ($measurement->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Brak dostępu.');
        }

        $this->em->remove($measurement);
        $this->em->flush();
    }
}
