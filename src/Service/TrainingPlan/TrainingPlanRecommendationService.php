<?php

namespace App\Service\TrainingPlan;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use App\Repository\WorkoutRepository;

class TrainingPlanRecommendationService
{
    public function __construct(
        private TrainingPlanRepository $planRepository,
        private WorkoutRepository $workoutRepository
    ) {}

    public function getRecommendedWorkout(User $user, ?\DateTimeInterface $now = null): ?array
    {
        $plan = $this->planRepository->findActivePlan($user);
        if (!$plan) {
            return null;
        }

        $now = $now ? \DateTime::createFromInterface($now) : new \DateTime();
        $cycleDays = $plan->getCycleDays();

        // Pobieramy wszystkie pozycje z planu będące treningami siłowymi
        $allWorkouts = [];
        $workoutDaysMap = [];
        foreach ($plan->getWorkouts() as $w) {
            if (!$w->isRestDay() && ($w->getActivityType() === 'WORKOUT' || $w->getActivityType() === null)) {
                $allWorkouts[] = $w;
                $dayNum = $w->getDayNumber() ?? 1;
                if (!isset($workoutDaysMap[$dayNum])) {
                    $workoutDaysMap[$dayNum] = [];
                }
                $workoutDaysMap[$dayNum][] = $w;
            }
        }

        if (empty($allWorkouts)) {
            return [
                'hasActivePlan' => true,
                'plan' => $plan,
                'status' => 'EMPTY_PLAN',
                'reason' => 'Aktywny cykl nie zawiera jeszcze żadnych zaplanowanych treningów siłowych.',
                'recommendedWorkout' => null,
                'allPlanWorkouts' => [],
            ];
        }

        $workoutDayNumbers = array_keys($workoutDaysMap);
        sort($workoutDayNumbers);

        $dayNames = [
            1 => 'poniedziałku',
            2 => 'wtorku',
            3 => 'środy',
            4 => 'czwartku',
            5 => 'piątku',
            6 => 'soboty',
            7 => 'niedzieli',
        ];

        $dayNamesNom = [
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            6 => 'Sobota',
            7 => 'Niedziela',
        ];

        // Pobieramy historię ukończonych sesji użytkownika dla tego planu
        $completedWorkouts = $this->workoutRepository->findCompletedWorkoutsForPlan($user, $plan);

        $status = 'NEXT_IN_CYCLE';
        $reason = '';
        $recommendedWorkout = null;

        if ($cycleDays === 7) {
            $todayDow = (int) $now->format('N'); // 1..7 (Poniedziałek..Niedziela)
            $startOfWeek = (clone $now)->setTime(0, 0, 0)->modify('monday this week');

            // Treningi ukończone w bieżącym tygodniu
            $completedThisWeek = $this->workoutRepository->findCompletedWorkoutsForPlan($user, $plan, $startOfWeek);
            $completedDaysThisWeek = [];
            foreach ($completedThisWeek as $cw) {
                if ($cw->getDayNumber()) {
                    $completedDaysThisWeek[$cw->getDayNumber()] = true;
                }
            }

            // 1. Sprawdzamy czy użytkownik "zaspał" i pominął treningi przed dniem dzisiejszym w tym tygodniu
            foreach ($workoutDayNumbers as $dNum) {
                if ($dNum < $todayDow && !isset($completedDaysThisWeek[$dNum])) {
                    $status = 'OVERDUE';
                    $dayNameGen = $dayNames[$dNum] ?? "dnia $dNum";
                    $reason = sprintf('Zaległy trening z %s – wykonaj go w pierwszej kolejności, aby utrzymać ciągłość planu.', $dayNameGen);
                    $recommendedWorkout = $workoutDaysMap[$dNum][0];
                    break;
                }
            }

            // 2. Jeśli brak zaległości, sprawdzamy czy dzisiaj wypada trening
            if (!$recommendedWorkout && in_array($todayDow, $workoutDayNumbers, true)) {
                if (!isset($completedDaysThisWeek[$todayDow])) {
                    $status = 'TODAY';
                    $reason = 'Twój zaplanowany trening na dziś. Gotowy do startu!';
                    $recommendedWorkout = $workoutDaysMap[$todayDow][0];
                }
            }

            // 3. Jeśli dzisiaj nie ma treningu lub został już zrobiony, szukamy kolejnego w cyklu
            if (!$recommendedWorkout) {
                $lastDayNum = null;
                if (!empty($completedWorkouts)) {
                    $lastDayNum = $completedWorkouts[0]->getDayNumber();
                }

                if ($lastDayNum !== null) {
                    for ($step = 1; $step <= 7; $step++) {
                        $candidate = (($lastDayNum - 1 + $step) % 7) + 1;
                        if (isset($workoutDaysMap[$candidate])) {
                            if ($candidate === $todayDow && isset($completedDaysThisWeek[$todayDow])) {
                                continue;
                            }
                            $recommendedWorkout = $workoutDaysMap[$candidate][0];
                            $status = 'NEXT_IN_CYCLE';
                            $dayNameNom = $dayNamesNom[$candidate] ?? "Dzień $candidate";
                            $reason = sprintf('Kolejny trening w Twoim cyklu (%s).', $dayNameNom);
                            break;
                        }
                    }
                } else {
                    foreach ($workoutDayNumbers as $dNum) {
                        if ($dNum >= $todayDow) {
                            $recommendedWorkout = $workoutDaysMap[$dNum][0];
                            $status = ($dNum === $todayDow) ? 'TODAY' : 'NEXT_IN_CYCLE';
                            $reason = ($dNum === $todayDow)
                                ? 'Twój zaplanowany trening na dziś. Rozpocznij swój cykl!'
                                : sprintf('Najbliższy trening w cyklu (%s).', $dayNamesNom[$dNum] ?? "Dzień $dNum");
                            break;
                        }
                    }
                    if (!$recommendedWorkout) {
                        $recommendedWorkout = $workoutDaysMap[$workoutDayNumbers[0]][0];
                        $status = 'NEXT_IN_CYCLE';
                        $reason = sprintf('Rozpocznij swój cykl treningowy od: %s.', $recommendedWorkout->getName());
                    }
                }
            }
        } else {
            // Cykl o innej długości niż 7 dni
            if (empty($completedWorkouts)) {
                $recommendedWorkout = $workoutDaysMap[$workoutDayNumbers[0]][0];
                $status = 'NEXT_IN_CYCLE';
                $reason = sprintf('Rozpocznij swój cykl od: %s.', $recommendedWorkout->getName());
            } else {
                $lastCompleted = $completedWorkouts[0];
                $lastDayNum = $lastCompleted->getDayNumber() ?? $workoutDayNumbers[0];
                $lastDate = $lastCompleted->getDate() ?? $now;

                $todayMidnight = (clone $now)->setTime(0, 0, 0);
                $lastMidnight = (clone $lastDate)->setTime(0, 0, 0);
                $diff = $todayMidnight->diff($lastMidnight);
                $daysPassed = abs((int) $diff->format('%r%a'));

                if ($daysPassed > 1) {
                    for ($step = 1; $step < $daysPassed; $step++) {
                        $checkDay = (($lastDayNum - 1 + $step) % $cycleDays) + 1;
                        if (isset($workoutDaysMap[$checkDay])) {
                            $status = 'OVERDUE';
                            $reason = sprintf('Zaległy trening (Dzień %d w cyklu) – wykonaj go w pierwszej kolejności.', $checkDay);
                            $recommendedWorkout = $workoutDaysMap[$checkDay][0];
                            break;
                        }
                    }
                }

                if (!$recommendedWorkout) {
                    $expectedTodayDay = (($lastDayNum - 1 + max(1, $daysPassed)) % $cycleDays) + 1;
                    if (isset($workoutDaysMap[$expectedTodayDay]) && $daysPassed >= 1) {
                        $status = 'TODAY';
                        $reason = sprintf('Zaplanowany trening na dziś (Dzień %d w cyklu).', $expectedTodayDay);
                        $recommendedWorkout = $workoutDaysMap[$expectedTodayDay][0];
                    } else {
                        for ($step = 1; $step <= $cycleDays; $step++) {
                            $candidate = (($lastDayNum - 1 + $step) % $cycleDays) + 1;
                            if (isset($workoutDaysMap[$candidate])) {
                                if ($daysPassed === 0 && $candidate === $lastDayNum) {
                                    continue;
                                }
                                $recommendedWorkout = $workoutDaysMap[$candidate][0];
                                $status = 'NEXT_IN_CYCLE';
                                $reason = sprintf('Kolejny trening w cyklu (Dzień %d).', $candidate);
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (!$recommendedWorkout && !empty($allWorkouts)) {
            $recommendedWorkout = $allWorkouts[0];
            $status = 'NEXT_IN_CYCLE';
            $reason = 'Kolejny trening z Twojego planu.';
        }

        return [
            'hasActivePlan' => true,
            'plan' => $plan,
            'status' => $status,
            'reason' => $reason,
            'recommendedWorkout' => $recommendedWorkout,
            'allPlanWorkouts' => $allWorkouts,
        ];
    }
}
