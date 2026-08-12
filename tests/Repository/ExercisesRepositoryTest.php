<?php

namespace App\Tests\Repository;

use App\Entity\ExerciseMuscle;
use App\Entity\Exercises;
use App\Entity\Muscles;
use App\Entity\BodyParts;
use App\Enum\MuscleActivationLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExercisesRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testFindAllWithMuscles(): void
    {
        $bodyPart = new BodyParts();
        $bodyPart->setName('Klatka piersiowa');
        
        $muscle = new Muscles();
        $muscle->setName('Klatka piersiowa większa');

        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Exercises')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Muscles')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\BodyParts')->execute();

        $exercises = $this->entityManager->getRepository(Exercises::class)->findAllWithMuscles();

        $this->assertIsArray($exercises);

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
