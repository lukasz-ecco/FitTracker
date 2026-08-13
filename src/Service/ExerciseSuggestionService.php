<?php

namespace App\Service;

use App\Entity\Exercises;
use App\Entity\TrainingGoal;
use App\Repository\ExercisesRepository;

class ExerciseSuggestionService
{
    public function __construct(
        private ExercisesRepository $exercisesRepository
    ) {}

    /**
     * Zwraca unikalną listę ćwiczeń pasujących do aktywnych celów użytkownika.
     * Trudność ćwiczenia zależy od poziomu zaawansowania powiązanego z celem (1 -> max 3, 2 -> max 6, 3 -> max 9).
     *
     * @param TrainingGoal[] $goals
     * @return Exercises[]
     */
    public function getSuggestionsForGoals(array $goals): array
    {
        if (empty($goals)) {
            return [];
        }

        $suggestions = [];
        foreach ($goals as $goal) {
            $maxDifficulty = $goal->getFitnessLevel() * 3; // 1 -> 3, 2 -> 6, 3 -> 9
            $goalSuggestions = $this->exercisesRepository->findByGoalAndMaxDifficulty(
                $goal->getGoalType(),
                $maxDifficulty
            );

            foreach ($goalSuggestions as $exercise) {
                $suggestions[$exercise->getId()] = $exercise;
            }
        }

        // Sortowanie po nazwie ćwiczenia
        usort($suggestions, fn ($a, $b) => strcmp($a->getName(), $b->getName()));

        return $suggestions;
    }
}
