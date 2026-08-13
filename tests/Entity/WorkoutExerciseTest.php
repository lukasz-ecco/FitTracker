<?php

namespace App\Tests\Entity;

use App\Entity\Exercises;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use PHPUnit\Framework\TestCase;

class WorkoutExerciseTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $workoutExercise = new WorkoutExercise();
        $workout = new Workout();
        $exercise = new Exercises();

        $workoutExercise->setWorkout($workout);
        $workoutExercise->setExercise($exercise);
        $workoutExercise->setOrderIndex(1);
        $workoutExercise->setNotes('Focus on form');

        $this->assertSame($workout, $workoutExercise->getWorkout());
        $this->assertSame($exercise, $workoutExercise->getExercise());
        $this->assertSame(1, $workoutExercise->getOrderIndex());
        $this->assertSame('Focus on form', $workoutExercise->getNotes());
    }

    public function testAddAndRemoveWorkoutExerciseSet(): void
    {
        $workoutExercise = new WorkoutExercise();
        $set = new WorkoutExerciseSet();

        $this->assertCount(0, $workoutExercise->getWorkoutExerciseSets());

        $workoutExercise->addWorkoutExerciseSet($set);

        $this->assertCount(1, $workoutExercise->getWorkoutExerciseSets());
        $this->assertTrue($workoutExercise->getWorkoutExerciseSets()->contains($set));
        $this->assertSame($workoutExercise, $set->getWorkoutExercise());

        $workoutExercise->removeWorkoutExerciseSet($set);

        $this->assertCount(0, $workoutExercise->getWorkoutExerciseSets());
        $this->assertFalse($workoutExercise->getWorkoutExerciseSets()->contains($set));
        $this->assertNull($set->getWorkoutExercise());
    }
}
