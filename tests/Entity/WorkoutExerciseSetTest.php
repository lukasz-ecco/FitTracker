<?php

namespace App\Tests\Entity;

use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use PHPUnit\Framework\TestCase;

class WorkoutExerciseSetTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $set = new WorkoutExerciseSet();
        $workoutExercise = new WorkoutExercise();

        $set->setWorkoutExercise($workoutExercise);
        $set->setSetNumber(1);
        $set->setReps(10);
        $set->setWeight(80.5);
        $set->setTempo('3-0-1-0');
        $set->setCompleted(true);
        $set->setDropSet(false);

        $this->assertSame($workoutExercise, $set->getWorkoutExercise());
        $this->assertSame(1, $set->getSetNumber());
        $this->assertSame(10, $set->getReps());
        $this->assertSame(80.5, $set->getWeight());
        $this->assertSame('3-0-1-0', $set->getTempo());
        $this->assertTrue($set->isCompleted());
        $this->assertFalse($set->isDropSet());
    }

    public function testDropSetParentRelationship(): void
    {
        $parentSet = new WorkoutExerciseSet();
        $dropSet = new WorkoutExerciseSet();

        $dropSet->setDropSet(true);
        $dropSet->setParentSet($parentSet);

        $this->assertTrue($dropSet->isDropSet());
        $this->assertSame($parentSet, $dropSet->getParentSet());
    }
}
