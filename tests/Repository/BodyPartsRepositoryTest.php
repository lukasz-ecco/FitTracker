<?php

namespace App\Tests\Repository;

use App\Entity\BodyParts;
use App\Entity\Muscles;
use App\Repository\BodyPartsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BodyPartsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private BodyPartsRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(BodyParts::class);
        $this->cleanDatabase();
    }

    private function cleanDatabase(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Muscles')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Meseurments')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\BodyParts')->execute();
    }

    public function testPersistAndSortOrder(): void
    {
        $szyja = (new BodyParts())->setName('Szyja')->setSortOrder(10)->setIsMeasurable(true);
        $klatka = (new BodyParts())->setName('Klatka Piersiowa')->setSortOrder(20)->setIsMeasurable(true);
        $plecy = (new BodyParts())->setName('Plecy')->setSortOrder(30)->setIsMeasurable(false);
        $cardio = (new BodyParts())->setName('Ćwiczenia Cardio')->setSortOrder(999)->setIsMeasurable(false);

        $this->entityManager->persist($cardio);
        $this->entityManager->persist($klatka);
        $this->entityManager->persist($szyja);
        $this->entityManager->persist($plecy);
        $this->entityManager->flush();

        // Sprawdzenie sortowania domyślnego
        $ordered = $this->repository->findBy([], ['sortOrder' => 'ASC']);
        $this->assertCount(4, $ordered);
        $this->assertSame('Szyja', $ordered[0]->getName());
        $this->assertSame('Klatka Piersiowa', $ordered[1]->getName());
        $this->assertSame('Plecy', $ordered[2]->getName());
        $this->assertSame('Ćwiczenia Cardio', $ordered[3]->getName());

        // Sprawdzenie filtrowania tylko mierzalnych partii (bez Pleców i bez Cardio)
        $measurableOnly = $this->repository->findBy(['isMeasurable' => true], ['sortOrder' => 'ASC']);
        $this->assertCount(2, $measurableOnly);
        $this->assertSame(['Szyja', 'Klatka Piersiowa'], array_map(fn($bp) => $bp->getName(), $measurableOnly));
    }

    public function testAddMuscleToBodyPart(): void
    {
        $bodyPart = (new BodyParts())->setName('Ramiona')->setSortOrder(40);
        $muscle = (new Muscles())->setName('Biceps');

        $bodyPart->addMuscle($muscle);
        $this->entityManager->persist($bodyPart);
        $this->entityManager->persist($muscle);
        $this->entityManager->flush();

        $found = $this->repository->find($bodyPart->getId());
        $this->assertNotNull($found);
        $this->assertCount(1, $found->getMuscles());
        $this->assertSame('Biceps', $found->getMuscles()->first()->getName());
    }
}
