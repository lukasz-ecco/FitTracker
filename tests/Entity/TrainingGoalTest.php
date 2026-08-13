<?php

namespace App\Tests\Entity;

use App\Entity\TrainingGoal;
use App\Entity\User;
use App\Enum\TrainingGoalType;
use PHPUnit\Framework\TestCase;

class TrainingGoalTest extends TestCase
{
    public function testConstructorInitializesCorrectly(): void
    {
        $goal = new TrainingGoal();

        $this->assertTrue($goal->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $goal->getCreatedAt());
        // Różnica czasu nie powinna być większa niż kilka sekund
        $this->assertLessThan(5, time() - $goal->getCreatedAt()->getTimestamp());
    }

    public function testGettersAndSetters(): void
    {
        $goal = new TrainingGoal();
        $user = new User();
        $goalType = TrainingGoalType::MUSCLE_GAIN;
        $fitnessLevel = 2;
        $notes = 'Moje notatki treningowe';
        $createdAt = new \DateTimeImmutable('2026-01-01 12:00:00');

        $goal->setUser($user)
            ->setGoalType($goalType)
            ->setFitnessLevel($fitnessLevel)
            ->setIsActive(false)
            ->setNotes($notes)
            ->setCreatedAt($createdAt);

        $this->assertSame($user, $goal->getUser());
        $this->assertSame($goalType, $goal->getGoalType());
        $this->assertSame($fitnessLevel, $goal->getFitnessLevel());
        $this->assertFalse($goal->isActive());
        $this->assertSame($notes, $goal->getNotes());
        $this->assertSame($createdAt, $goal->getCreatedAt());
    }

    public function testFitnessLevelValues(): void
    {
        $goal = new TrainingGoal();
        
        $goal->setFitnessLevel(1);
        $this->assertSame(1, $goal->getFitnessLevel());

        $goal->setFitnessLevel(3);
        $this->assertSame(3, $goal->getFitnessLevel());
    }
}
