<?php

namespace App\Tests\Service;

use App\Entity\Exercises;
use App\Entity\TrainingGoal;
use App\Enum\TrainingGoalType;
use App\Repository\ExercisesRepository;
use App\Service\ExerciseSuggestionService;
use PHPUnit\Framework\TestCase;

class ExerciseSuggestionServiceTest extends TestCase
{
    public function testGetSuggestionsForMultipleGoals(): void
    {
        $exercisesRepo = $this->createMock(ExercisesRepository::class);

        $ex1 = $this->createMock(Exercises::class);
        $ex1->method('getId')->willReturn(1);
        $ex1->method('getName')->willReturn('Pompki');

        $ex2 = $this->createMock(Exercises::class);
        $ex2->method('getId')->willReturn(2);
        $ex2->method('getName')->willReturn('Przysiady');

        $exercisesRepo->expects($this->exactly(2))
            ->method('findByGoalAndMaxDifficulty')
            ->willReturnMap([
                [TrainingGoalType::MUSCLE_GAIN, 6, [$ex1]],
                [TrainingGoalType::WEIGHT_LOSS, 3, [$ex1, $ex2]], // Pompki i Przysiady, Pompki to duplikat
            ]);

        $goal1 = new TrainingGoal();
        $goal1->setGoalType(TrainingGoalType::MUSCLE_GAIN);
        $goal1->setFitnessLevel(2); // level 2 -> max difficulty = 6

        $goal2 = new TrainingGoal();
        $goal2->setGoalType(TrainingGoalType::WEIGHT_LOSS);
        $goal2->setFitnessLevel(1); // level 1 -> max difficulty = 3

        $service = new ExerciseSuggestionService($exercisesRepo);
        $result = $service->getSuggestionsForGoals([$goal1, $goal2]);

        // Powinno zwrócić 2 unikalne ćwiczenia (Pompki i Przysiady)
        $this->assertCount(2, $result);
        $this->assertSame($ex1, $result[0]); // Sortowane alfabetycznie: Pompki, Przysiady
        $this->assertSame($ex2, $result[1]);
    }

    public function testGetSuggestionsForEmptyGoals(): void
    {
        $exercisesRepo = $this->createMock(ExercisesRepository::class);
        $exercisesRepo->expects($this->never())
            ->method('findByGoalAndMaxDifficulty');

        $service = new ExerciseSuggestionService($exercisesRepo);
        $result = $service->getSuggestionsForGoals([]);

        $this->assertEmpty($result);
    }
}
