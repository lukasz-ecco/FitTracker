<?php

namespace App\Tests\Service;

use App\Entity\BodyParts;
use App\Entity\Meseurments;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\MeasurementService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MeasurementServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;
    private MeasurementService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->service = new MeasurementService($this->em, $this->validator);
    }

    public function testUpdateMeasurementThrowsAccessDenied(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);
        
        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(2);

        $measurement = new Meseurments();
        $measurement->setUser($user1);

        $this->expectException(AccessDeniedException::class);
        $this->service->updateMeasurement($measurement, $user2, []);
    }

    public function testUpdateMeasurementThrowsInvalidArgumentIfOlderThan7Days(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $measurement = new Meseurments();
        $measurement->setUser($user);
        
        $oldDate = new \DateTime('-8 days');
        $measurement->setDate($oldDate);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nie można edytować pomiarów starszych niż 7 dni.');

        $this->service->updateMeasurement($measurement, $user, ['size' => 10]);
    }

    public function testUpdateMeasurementSucceeds(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $measurement = new Meseurments();
        $measurement->setUser($user);
        $measurement->setDate(new \DateTime('-2 days'));
        
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $result = $this->service->updateMeasurement($measurement, $user, ['size' => 15]);

        $this->assertEquals(15, $result->getSize());
    }
}
