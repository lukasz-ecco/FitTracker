<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Entity\Workout;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutRepository;
use App\Service\DashboardService;
use App\Service\TrainingPlan\TrainingPlanRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    private TrainingPlanRecommendationService $recommendationService;
    private WorkoutRepository $workoutRepository;
    private WeightLogRepository $weightLogRepository;
    private EntityManagerInterface $em;
    private DashboardService $service;

    protected function setUp(): void
    {
        $this->recommendationService = $this->createMock(TrainingPlanRecommendationService::class);
        $this->workoutRepository = $this->createMock(WorkoutRepository::class);
        $this->weightLogRepository = $this->createMock(WeightLogRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new DashboardService(
            $this->recommendationService,
            $this->workoutRepository,
            $this->weightLogRepository,
            $this->em
        );
    }

    public function testGetDashboardSummaryCalculatesBmiAndAggregatesCompletedWorkouts(): void
    {
        $user = new User();
        $user->setHeight(180);
        $user->setWeight(80);

        $this->recommendationService->expects($this->once())
            ->method('getRecommendedWorkout')
            ->willReturn([
                'hasActivePlan' => true,
                'status' => 'TODAY',
                'reason' => 'Dzisiejszy trening',
            ]);

        $w1 = new Workout();
        $w1->setName('Trening A');
        $w1->setDuration(45);
        $w1->setVolume(2500.0);
        $w1->setStatus('COMPLETED');
        $w1->setDate(new \DateTime());

        $w2 = new Workout();
        $w2->setName('Trening B');
        $w2->setDuration(30);
        $w2->setVolume(1200.0);
        $w2->setStatus('COMPLETED');
        $w2->setDate(new \DateTime());

        $this->workoutRepository->expects($this->once())
            ->method('findTodayCompletedWorkouts')
            ->willReturn([$w1, $w2]);

        $log1 = new WeightLog();
        $log1->setWeight(82.0);
        $log1->setDate(new \DateTimeImmutable('-7 days'));

        $log2 = new WeightLog();
        $log2->setWeight(80.5);
        $log2->setDate(new \DateTimeImmutable('today'));

        $this->weightLogRepository->expects($this->once())
            ->method('findUserWeightHistoryChronological')
            ->willReturn([$log1, $log2]);

        $summary = $this->service->getDashboardSummary($user);

        // Asercje rekomendacji
        $this->assertEquals('TODAY', $summary['todayPlan']['status']);

        // Asercje dzisiejszych ukończonych
        $this->assertEquals(2, $summary['todayCompleted']['count']);
        $this->assertEquals(75, $summary['todayCompleted']['totalDurationMinutes']);
        $this->assertEquals(3700.0, $summary['todayCompleted']['totalVolumeKg']);
        $this->assertCount(2, $summary['todayCompleted']['workouts']);

        // Asercje wagi i BMI: 80.5 / (1.8^2) = 24.8 -> Waga prawidłowa
        $this->assertEquals(80.5, $summary['weightSummary']['currentWeight']);
        $this->assertEquals(180.0, $summary['weightSummary']['height']);
        $this->assertEquals(24.8, $summary['weightSummary']['bmi']);
        $this->assertEquals('Waga prawidłowa', $summary['weightSummary']['bmiCategory']);
        $this->assertEquals(-1.5, $summary['weightSummary']['weightChange']); // 80.5 - 82.0 = -1.5
        $this->assertCount(2, $summary['weightSummary']['history']);
    }

    public function testRecordWeightPersistsLogAndUpdatesUserProfile(): void
    {
        $user = new User();
        $user->setWeight(75);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $log = $this->service->recordWeight($user, 74.2, new \DateTime('2026-09-15'), 'Ranny pomiar');

        $this->assertEquals(74.2, $log->getWeight());
        $this->assertEquals('Ranny pomiar', $log->getNotes());
        $this->assertEquals(74, $user->getWeight());
    }
}
