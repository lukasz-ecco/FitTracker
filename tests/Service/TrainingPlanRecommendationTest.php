<?php

namespace App\Tests\Service;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\TrainingPlanRepository;
use App\Repository\WorkoutRepository;
use App\Service\TrainingPlan\TrainingPlanRecommendationService;
use PHPUnit\Framework\TestCase;

class TrainingPlanRecommendationTest extends TestCase
{
    private $workoutRepo;
    private $planRepo;
    private TrainingPlanRecommendationService $service;

    protected function setUp(): void
    {
        $this->workoutRepo = $this->createMock(WorkoutRepository::class);
        $this->planRepo = $this->createMock(TrainingPlanRepository::class);

        $this->service = new TrainingPlanRecommendationService(
            $this->planRepo,
            $this->workoutRepo
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setValue($entity, $id);
    }

    public function testRecommendationReturnsTodayWorkoutWhenScheduled(): void
    {
        $user = new User();
        $this->setEntityId($user, 1);

        $plan = new TrainingPlan();
        $this->setEntityId($plan, 10);
        $plan->setUser($user);
        $plan->setCycleDays(7);
        $plan->setIsActive(true);

        // Poniedziałek (Day 1) - Trening
        $day1 = new Workout();
        $this->setEntityId($day1, 101);
        $day1->setName('Trening A - Klatka');
        $day1->setDayNumber(1);
        $day1->setIsRestDay(false);
        $day1->setActivityType('WORKOUT');
        $plan->addWorkout($day1);

        // Wtorek (Day 2) - Trening
        $day2 = new Workout();
        $this->setEntityId($day2, 102);
        $day2->setName('Trening B - Plecy');
        $day2->setDayNumber(2);
        $day2->setIsRestDay(false);
        $day2->setActivityType('WORKOUT');
        $plan->addWorkout($day2);

        $this->planRepo->method('findActivePlan')->with($user)->willReturn($plan);

        // Symulacja: Dzisiaj jest wtorek (2026-09-15 to wtorek)
        $tuesday = new \DateTime('2026-09-15 10:00:00');

        // Poniedziałek był ukończony
        $completedDay1 = new Workout();
        $completedDay1->setDayNumber(1);
        $completedDay1->setStatus('COMPLETED');
        $completedDay1->setDate(new \DateTime('2026-09-14 18:00:00'));

        $this->workoutRepo->method('findCompletedWorkoutsForPlan')
            ->willReturnCallback(function ($u, $p, $since = null) use ($completedDay1) {
                return [$completedDay1];
            });

        $res = $this->service->getRecommendedWorkout($user, $tuesday);

        $this->assertNotNull($res);
        $this->assertEquals('TODAY', $res['status']);
        $this->assertEquals(102, $res['recommendedWorkout']->getId());
        $this->assertEquals('Trening B - Plecy', $res['recommendedWorkout']->getName());
    }

    public function testRecommendationPrioritizesOverdueWorkoutWhenUserSleptIn(): void
    {
        $user = new User();
        $this->setEntityId($user, 1);

        $plan = new TrainingPlan();
        $this->setEntityId($plan, 10);
        $plan->setUser($user);
        $plan->setCycleDays(7);
        $plan->setIsActive(true);

        // Poniedziałek (Day 1) - Trening
        $day1 = new Workout();
        $this->setEntityId($day1, 101);
        $day1->setName('Trening Poniedziałkowy');
        $day1->setDayNumber(1);
        $day1->setIsRestDay(false);
        $day1->setActivityType('WORKOUT');
        $plan->addWorkout($day1);

        // Środa (Day 3) - Trening
        $day3 = new Workout();
        $this->setEntityId($day3, 103);
        $day3->setName('Trening Środowy');
        $day3->setDayNumber(3);
        $day3->setIsRestDay(false);
        $day3->setActivityType('WORKOUT');
        $plan->addWorkout($day3);

        $this->planRepo->method('findActivePlan')->with($user)->willReturn($plan);

        // Symulacja: Dzisiaj jest środa (2026-09-16)
        $wednesday = new \DateTime('2026-09-16 10:00:00');

        // Użytkownik zaspał i w poniedziałek NIE zrobił treningu (brak ukończonych w tym tygodniu)
        $this->workoutRepo->method('findCompletedWorkoutsForPlan')->willReturn([]);

        $res = $this->service->getRecommendedWorkout($user, $wednesday);

        $this->assertNotNull($res);
        $this->assertEquals('OVERDUE', $res['status']);
        $this->assertEquals(101, $res['recommendedWorkout']->getId());
        $this->assertEquals('Trening Poniedziałkowy', $res['recommendedWorkout']->getName());
        $this->assertStringContainsString('Zaległy trening z poniedziałku', $res['reason']);
    }

    public function testRecommendationSuggestsNextInCycleWhenTodayIsRestDay(): void
    {
        $user = new User();
        $this->setEntityId($user, 1);

        $plan = new TrainingPlan();
        $this->setEntityId($plan, 10);
        $plan->setUser($user);
        $plan->setCycleDays(7);
        $plan->setIsActive(true);

        // Poniedziałek (Day 1) - Trening
        $day1 = new Workout();
        $this->setEntityId($day1, 101);
        $day1->setName('Trening Poniedziałkowy');
        $day1->setDayNumber(1);
        $day1->setIsRestDay(false);
        $day1->setActivityType('WORKOUT');
        $plan->addWorkout($day1);

        // Wtorek (Day 2) - Rest Day
        $day2 = new Workout();
        $this->setEntityId($day2, 102);
        $day2->setName('Rest Day');
        $day2->setDayNumber(2);
        $day2->setIsRestDay(true);
        $day2->setActivityType('FULL_REST');
        $plan->addWorkout($day2);

        // Środa (Day 3) - Trening
        $day3 = new Workout();
        $this->setEntityId($day3, 103);
        $day3->setName('Trening Środowy');
        $day3->setDayNumber(3);
        $day3->setIsRestDay(false);
        $day3->setActivityType('WORKOUT');
        $plan->addWorkout($day3);

        $this->planRepo->method('findActivePlan')->with($user)->willReturn($plan);

        // Symulacja: Dzisiaj jest wtorek (2026-09-15) - rest day
        $tuesday = new \DateTime('2026-09-15 12:00:00');

        // Poniedziałek został zrobiony
        $completedDay1 = new Workout();
        $completedDay1->setDayNumber(1);
        $completedDay1->setStatus('COMPLETED');
        $completedDay1->setDate(new \DateTime('2026-09-14 18:00:00'));

        $this->workoutRepo->method('findCompletedWorkoutsForPlan')
            ->willReturnCallback(function ($u, $p, $since = null) use ($completedDay1) {
                return [$completedDay1];
            });

        $res = $this->service->getRecommendedWorkout($user, $tuesday);

        $this->assertNotNull($res);
        $this->assertEquals('NEXT_IN_CYCLE', $res['status']);
        $this->assertEquals(103, $res['recommendedWorkout']->getId());
        $this->assertEquals('Trening Środowy', $res['recommendedWorkout']->getName());
        $this->assertStringContainsString('Kolejny trening w Twoim cyklu', $res['reason']);
    }
}
