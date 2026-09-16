<?php

namespace App\Service\TrainingPlan;

use App\Entity\TrainingPlan;

class TrainingPlanCycleAnalyzer
{
    public function analyzeCycle(TrainingPlan $plan): array
    {
        $cycleDays = $plan->getCycleDays();
        $workoutsByDay = [];
        for ($d = 1; $d <= $cycleDays; $d++) {
            $workoutsByDay[$d] = [];
        }

        foreach ($plan->getWorkouts() as $w) {
            $dayNum = $w->getDayNumber();
            if ($dayNum && $dayNum >= 1 && $dayNum <= $cycleDays) {
                $workoutsByDay[$dayNum][] = $w;
            }
        }

        $workoutDays = [];
        $restDays = [];
        $activeRecoveryDays = [];

        for ($d = 1; $d <= $cycleDays; $d++) {
            $dayItems = $workoutsByDay[$d];
            $hasWorkout = false;
            $hasActiveRecovery = false;

            foreach ($dayItems as $item) {
                if (!$item->isRestDay() && ($item->getActivityType() === 'WORKOUT' || $item->getActivityType() === null)) {
                    $hasWorkout = true;
                } elseif ($item->isRestDay() && $item->getActivityType() !== 'FULL_REST' && $item->getActivityType() !== null) {
                    $hasActiveRecovery = true;
                }
            }

            if ($hasWorkout) {
                $workoutDays[] = $d;
            } elseif ($hasActiveRecovery) {
                $activeRecoveryDays[] = $d;
                $restDays[] = $d;
            } else {
                $restDays[] = $d;
            }
        }

        // Kalkulacja przerw pomiędzy dniami treningowymi (cyklicznie)
        $intervals = [];
        if (count($workoutDays) >= 1) {
            for ($i = 0; $i < count($workoutDays); $i++) {
                $currentWorkoutDay = $workoutDays[$i];
                $nextWorkoutDay = ($i + 1 < count($workoutDays)) ? $workoutDays[$i + 1] : $workoutDays[0];

                if ($nextWorkoutDay > $currentWorkoutDay) {
                    $restDaysCount = ($nextWorkoutDay - $currentWorkoutDay) - 1;
                } else {
                    $restDaysCount = ($cycleDays - $currentWorkoutDay) + ($nextWorkoutDay - 1);
                }

                $activitiesInBetween = [];
                for ($step = 1; $step <= $restDaysCount; $step++) {
                    $dayIndex = (($currentWorkoutDay - 1 + $step) % $cycleDays) + 1;
                    foreach ($workoutsByDay[$dayIndex] as $item) {
                        $activitiesInBetween[] = [
                            'dayNumber' => $dayIndex,
                            'name' => $item->getName(),
                            'activityType' => $item->getActivityType() ?? 'FULL_REST',
                            'duration' => $item->getPlannedDurationMinutes(),
                            'distance' => $item->getPlannedDistanceKm(),
                        ];
                    }
                }

                $intervals[] = [
                    'fromDay' => $currentWorkoutDay,
                    'toDay' => $nextWorkoutDay,
                    'restDaysCount' => $restDaysCount,
                    'activities' => $activitiesInBetween,
                ];
            }
        }

        $recommendation = null;
        if (count($workoutDays) === 0) {
            $recommendation = 'Plan nie zawiera jeszcze żadnego dnia treningowego.';
        } elseif (count($restDays) === 0) {
            $recommendation = 'Plan nie zawiera dni odpoczynku. Zaleca się zaplanowanie przynajmniej 1-2 dni regeneracji w cyklu.';
        } else {
            $minRest = null;
            foreach ($intervals as $interval) {
                if ($minRest === null || $interval['restDaysCount'] < $minRest) {
                    $minRest = $interval['restDaysCount'];
                }
            }
            if ($minRest === 0) {
                $recommendation = 'Plan zawiera treningi dzień po dniu bez przerwy. Pamiętaj o regeneracji mięśni.';
            } else {
                $recommendation = sprintf('Rozkład zrównoważony. Pomiędzy treningami zaplanowano min. %d dni odpoczynku/aktywności.', $minRest);
            }
        }

        return [
            'cycleDays' => $cycleDays,
            'workoutDaysCount' => count($workoutDays),
            'restDaysCount' => count($restDays),
            'activeRecoveryDaysCount' => count($activeRecoveryDays),
            'workoutDays' => $workoutDays,
            'intervals' => $intervals,
            'recommendation' => $recommendation,
        ];
    }
}
