<?php

namespace App\Tests\Entity;

use App\Entity\Exercises;
use App\Entity\TrainingPlan;
use App\Entity\TrainingPlanDay;
use App\Entity\TrainingPlanDayExercise;
use App\Entity\TrainingPlanDayExerciseSet;
use App\Entity\WorkoutTemplate;
use PHPUnit\Framework\TestCase;

class TrainingPlanDayTest extends TestCase
{
    public function testTrainingPlanDayPropertiesAndRelations(): void
    {
        $plan = new TrainingPlan();
        $plan->setName('Plan testowy');

        $day = new TrainingPlanDay();
        $day->setTrainingPlan($plan);
        $day->setDayNumber(1);
        $day->setName('Push Day');
        $day->setDescription('Opis treningu Push');
        $day->setIsRestDay(false);
        $day->setActivityType('WORKOUT');
        $day->setPlannedDurationMinutes(60);
        $day->setPlannedDistanceKm(null);

        $template = new WorkoutTemplate();
        $template->setName('Szablon Push');
        $day->setTemplate($template);

        $this->assertSame($plan, $day->getTrainingPlan());
        $this->assertEquals(1, $day->getDayNumber());
        $this->assertEquals('Push Day', $day->getName());
        $this->assertEquals('Opis treningu Push', $day->getDescription());
        $this->assertFalse($day->isRestDay());
        $this->assertEquals('WORKOUT', $day->getActivityType());
        $this->assertEquals(60, $day->getPlannedDurationMinutes());
        $this->assertNull($day->getPlannedDistanceKm());
        $this->assertSame($template, $day->getTemplate());
        $this->assertEquals('PLANNED', $day->getStatus());

        // Test dodawania ćwiczeń
        $exercise = new Exercises();
        $exercise->setName('Wyciskanie na ławce');

        $dayExercise = new TrainingPlanDayExercise();
        $dayExercise->setExercise($exercise);
        $dayExercise->setOrderIndex(1);
        $dayExercise->setNotes('Tempo 3010');

        $day->addExercise($dayExercise);
        $this->assertCount(1, $day->getExercises());
        $this->assertCount(1, $day->getWorkoutExercises());
        $this->assertSame($day, $dayExercise->getPlanDay());

        // Test dodawania serii
        $set1 = new TrainingPlanDayExerciseSet();
        $set1->setSetNumber(1);
        $set1->setReps(10);
        $set1->setWeight(80.0);
        $set1->setTempo('3010');
        $set1->setIsDropSet(false);

        $dayExercise->addSet($set1);
        $this->assertCount(1, $dayExercise->getSets());
        $this->assertCount(1, $dayExercise->getWorkoutExerciseSets());
        $this->assertSame($dayExercise, $set1->getPlanDayExercise());

        // Test usuwania ćwiczenia
        $day->removeExercise($dayExercise);
        $this->assertCount(0, $day->getExercises());
    }

    public function testRestDayActivityTypes(): void
    {
        $day = new TrainingPlanDay();
        $day->setDayNumber(2);
        $day->setName('Dzień spaceru');
        $day->setIsRestDay(true);
        $day->setActivityType('WALK');
        $day->setPlannedDurationMinutes(45);
        $day->setPlannedDistanceKm(4.5);

        $this->assertTrue($day->isRestDay());
        $this->assertEquals('WALK', $day->getActivityType());
        $this->assertEquals(45, $day->getPlannedDurationMinutes());
        $this->assertEquals(4.5, $day->getPlannedDistanceKm());
    }
}
