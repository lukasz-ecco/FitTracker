<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutRepository;
use App\Service\TrainingPlan\TrainingPlanRecommendationService;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private readonly TrainingPlanRecommendationService $recommendationService,
        private readonly WorkoutRepository $workoutRepository,
        private readonly WeightLogRepository $weightLogRepository,
        private readonly EntityManagerInterface $em
    ) {}

    public function getDashboardSummary(User $user, ?\DateTimeInterface $now = null): array
    {
        $currentDate = $now ? (clone $now) : new \DateTime();

        // 1. Aktywności z planu na dzisiaj / rekomendacje
        $planRecommendation = $this->recommendationService->getRecommendedWorkout($user, $currentDate);

        // 2. Dzisiejsze ukończone aktywności
        $todayWorkouts = $this->workoutRepository->findTodayCompletedWorkouts($user, $currentDate);
        $totalDuration = 0;
        $totalVolume = 0.0;
        $completedWorkoutsList = [];

        foreach ($todayWorkouts as $w) {
            $duration = (int) ($w->getDuration() ?? 0);
            $volume = (float) ($w->getVolume() ?? 0.0);
            $totalDuration += $duration;
            $totalVolume += $volume;

            $completedWorkoutsList[] = [
                'id' => $w->getId(),
                'name' => $w->getName(),
                'duration' => $duration,
                'volume' => $volume,
                'date' => $w->getDate()?->format('Y-m-d H:i:s'),
                'activityType' => $w->getActivityType() ?? ($w->isRestDay() ? 'REST' : 'WORKOUT'),
            ];
        }

        // 3. Parametry wagi i historia
        $weightHistory = $this->weightLogRepository->findUserWeightHistoryChronological($user, 14);
        $currentWeight = null;
        if (!empty($weightHistory)) {
            $lastLog = end($weightHistory);
            $currentWeight = $lastLog->getWeight();
        } elseif ($user->getWeight()) {
            $currentWeight = (float) $user->getWeight();
        }

        $height = $user->getHeight() ? (float) $user->getHeight() : null;
        $bmi = null;
        $bmiCategory = null;

        if ($currentWeight && $height && $height > 0) {
            $heightMeters = $height / 100.0;
            $bmi = round($currentWeight / ($heightMeters * $heightMeters), 1);

            if ($bmi < 18.5) {
                $bmiCategory = 'Niedowaga';
            } elseif ($bmi < 25.0) {
                $bmiCategory = 'Waga prawidłowa';
            } elseif ($bmi < 30.0) {
                $bmiCategory = 'Nadwaga';
            } else {
                $bmiCategory = 'Otyłość';
            }
        }

        $weightChange = 0.0;
        if (count($weightHistory) >= 2) {
            $first = $weightHistory[0]->getWeight();
            $last = end($weightHistory)->getWeight();
            $weightChange = round($last - $first, 1);
        }

        $historyFormatted = [];
        foreach ($weightHistory as $log) {
            $historyFormatted[] = [
                'id' => $log->getId(),
                'weight' => $log->getWeight(),
                'date' => $log->getDate()->format('Y-m-d'),
                'notes' => $log->getNotes(),
            ];
        }

        return [
            'todayPlan' => $planRecommendation,
            'todayCompleted' => [
                'count' => count($todayWorkouts),
                'totalDurationMinutes' => $totalDuration,
                'totalVolumeKg' => round($totalVolume, 1),
                'workouts' => $completedWorkoutsList,
            ],
            'weightSummary' => [
                'currentWeight' => $currentWeight,
                'height' => $height,
                'bmi' => $bmi,
                'bmiCategory' => $bmiCategory,
                'weightChange' => $weightChange,
                'history' => $historyFormatted,
            ],
        ];
    }

    public function recordWeight(User $user, float $weight, ?\DateTimeInterface $date = null, ?string $notes = null): WeightLog
    {
        $logDate = $date ? \DateTimeImmutable::createFromInterface($date) : new \DateTimeImmutable();

        $log = new WeightLog();
        $log->setUser($user);
        $log->setWeight($weight);
        $log->setDate($logDate);
        if ($notes !== null) {
            $log->setNotes($notes);
        }

        // Aktualizacja bieżącej wagi na profilu usera
        $user->setWeight((int) round($weight));

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }
}
