<?php

namespace App\Tests\Entity;

use App\Entity\ExerciseMuscle;
use App\Entity\Exercises;
use PHPUnit\Framework\TestCase;

class ExercisesTest extends TestCase
{
    public function testAddExerciseMuscle(): void
    {
        $exercise = new Exercises();
        $exerciseMuscle = new ExerciseMuscle();

        // Check initial state
        $this->assertCount(0, $exercise->getExerciseMuscles());
        $this->assertNull($exerciseMuscle->getExercise());

        // Add
        $exercise->addExerciseMuscle($exerciseMuscle);

        // Check after adding
        $this->assertCount(1, $exercise->getExerciseMuscles());
        $this->assertTrue($exercise->getExerciseMuscles()->contains($exerciseMuscle));
        $this->assertSame($exercise, $exerciseMuscle->getExercise());
    }

    public function testRemoveExerciseMuscle(): void
    {
        $exercise = new Exercises();
        $exerciseMuscle = new ExerciseMuscle();

        // Add first
        $exercise->addExerciseMuscle($exerciseMuscle);
        $this->assertCount(1, $exercise->getExerciseMuscles());

        // Remove
        $exercise->removeExerciseMuscle($exerciseMuscle);

        // Check after removing
        $this->assertCount(0, $exercise->getExerciseMuscles());
        $this->assertFalse($exercise->getExerciseMuscles()->contains($exerciseMuscle));
        $this->assertNull($exerciseMuscle->getExercise());
    }
}
