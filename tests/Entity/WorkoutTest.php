<?php

namespace App\Tests\Entity;

use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class WorkoutTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $workout = new Workout();
        $date = new \DateTime();
        $user = new User();
        $trainer = new User();

        $workout->setName('FBW');
        $workout->setDescription('Full body workout');
        $workout->setDate($date);
        $workout->setStatus('PLANNED');
        $workout->setUser($user);
        $workout->setTrainer($trainer);

        $this->assertSame('FBW', $workout->getName());
        $this->assertSame('Full body workout', $workout->getDescription());
        $this->assertSame($date, $workout->getDate());
        $this->assertSame('PLANNED', $workout->getStatus());
        $this->assertSame($user, $workout->getUser());
        $this->assertSame($trainer, $workout->getTrainer());
    }

    public function testAddAndRemoveWorkoutExercise(): void
    {
        $workout = new Workout();
        $exercise = new WorkoutExercise();

        $this->assertCount(0, $workout->getWorkoutExercises());

        $workout->addWorkoutExercise($exercise);

        $this->assertCount(1, $workout->getWorkoutExercises());
        $this->assertTrue($workout->getWorkoutExercises()->contains($exercise));
        $this->assertSame($workout, $exercise->getWorkout());

        $workout->removeWorkoutExercise($exercise);

        $this->assertCount(0, $workout->getWorkoutExercises());
        $this->assertFalse($workout->getWorkoutExercises()->contains($exercise));
        $this->assertNull($exercise->getWorkout());
    }
}
