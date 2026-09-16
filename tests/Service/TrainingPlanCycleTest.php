<?php

namespace App\Tests\Service;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Repository\TrainingPlanRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutTemplateRepository;
use App\Service\TrainingPlanService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TrainingPlanCycleTest extends TestCase
{
    private $em;
    private $validator;
    private $connectionRepo;
    private $userRepo;
    private $workoutRepo;
    private $planRepo;
    private $templateRepo;
    private TrainingPlanService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->connectionRepo = $this->createMock(TrainerTraineeConnectionRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->workoutRepo = $this->createMock(WorkoutRepository::class);
        $this->planRepo = $this->createMock(TrainingPlanRepository::class);
        $this->templateRepo = $this->createMock(WorkoutTemplateRepository::class);

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->service = new TrainingPlanService(
            $this->em,
            $this->validator,
            $this->connectionRepo,
            $this->userRepo,
            $this->workoutRepo,
            $this->planRepo,
            $this->templateRepo
        );
    }

    public function testUserCanSetCycleDaysAndMustFillAllDays(): void
    {
        $user = new User();
        $this->setUserId($user, 1);

        // Użytkownik ustala cykl 4-dniowy, ale podaje tylko dni 1 i 2 -> błąd
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Wszystkie 4 dni cyklu muszą zostać zdefiniowane. Brakujące dni: 3, 4.');

        $this->service->createPlan($user, [
            'name' => 'Cykl 4 dni',
            'cycleDays' => 4,
            'workouts' => [
                ['name' => 'Dzień 1 Trening', 'dayNumber' => 1, 'isRestDay' => false, 'activityType' => 'WORKOUT'],
                ['name' => 'Dzień 2 Spacer', 'dayNumber' => 2, 'isRestDay' => true, 'activityType' => 'WALK'],
            ]
        ]);
    }

    public function testUserCanCreatePlanWithMultipleActivitiesOnSameDayAndFullRestDay(): void
    {
        $user = new User();
        $this->setUserId($user, 1);

        // Plan 3-dniowy:
        // Dzień 1: Rano trening siłowy ORAZ Po południu spacer (2 pozycje w 1 dniu!)
        // Dzień 2: Pełny dzień odpoczynku (FULL_REST)
        // Dzień 3: Rower (CYCLING)
        $plan = $this->service->createPlan($user, [
            'name' => 'Cykl 3 dniowy',
            'cycleDays' => 3,
            'workouts' => [
                [
                    'name' => 'Klatka + Triceps',
                    'dayNumber' => 1,
                    'isRestDay' => false,
                    'activityType' => 'WORKOUT',
                ],
                [
                    'name' => 'Popołudniowy Spacer',
                    'dayNumber' => 1,
                    'isRestDay' => true,
                    'activityType' => 'WALK',
                    'plannedDurationMinutes' => 45,
                    'plannedDistanceKm' => 3.5,
                ],
                [
                    'name' => 'Regeneracja',
                    'dayNumber' => 2,
                    'isRestDay' => true,
                    'activityType' => 'FULL_REST',
                ],
                [
                    'name' => 'Lekki Rower',
                    'dayNumber' => 3,
                    'isRestDay' => true,
                    'activityType' => 'CYCLING',
                    'plannedDurationMinutes' => 60,
                    'plannedDistanceKm' => 18.0,
                ],
            ]
        ]);

        $this->assertEquals(3, $plan->getCycleDays());
        $workouts = $plan->getWorkouts();
        $this->assertCount(4, $workouts);

        // Sprawdzenie dnia 1 (2 pozycje)
        $day1Items = array_values(array_filter($workouts->toArray(), fn($w) => $w->getDayNumber() === 1));
        $this->assertCount(2, $day1Items);
        $this->assertEquals('WORKOUT', $day1Items[0]->getActivityType());
        $this->assertFalse($day1Items[0]->isRestDay());

        $this->assertEquals('WALK', $day1Items[1]->getActivityType());
        $this->assertTrue($day1Items[1]->isRestDay());
        $this->assertEquals(45, $day1Items[1]->getPlannedDurationMinutes());
        $this->assertEquals(3.5, $day1Items[1]->getPlannedDistanceKm());

        // Sprawdzenie dnia 2 (Pełny rest)
        $day2Items = array_values(array_filter($workouts->toArray(), fn($w) => $w->getDayNumber() === 2));
        $this->assertCount(1, $day2Items);
        $this->assertEquals('FULL_REST', $day2Items[0]->getActivityType());
        $this->assertTrue($day2Items[0]->isRestDay());

        // Sprawdzenie dnia 3 (Rower)
        $day3Items = array_values(array_filter($workouts->toArray(), fn($w) => $w->getDayNumber() === 3));
        $this->assertCount(1, $day3Items);
        $this->assertEquals('CYCLING', $day3Items[0]->getActivityType());
        $this->assertTrue($day3Items[0]->isRestDay());
        $this->assertEquals(60, $day3Items[0]->getPlannedDurationMinutes());
        $this->assertEquals(18.0, $day3Items[0]->getPlannedDistanceKm());
    }

    public function testCycleAnalysisCalculatesRestDaysBetweenWorkouts(): void
    {
        $user = new User();
        $this->setUserId($user, 1);

        // Plan 7-dniowy:
        // Dzień 1: Trening A
        // Dzień 2: Spacer (Rest/Active)
        // Dzień 3: Pełny Rest
        // Dzień 4: Trening B
        // Dzień 5: Rower (Rest/Active)
        // Dzień 6: Pełny Rest
        // Dzień 7: Pełny Rest
        $plan = new TrainingPlan();
        $plan->setName('Plan 7-dniowy');
        $plan->setUser($user);
        $plan->setCycleDays(7);

        $w1 = new Workout();
        $w1->setName('Trening A');
        $w1->setDayNumber(1);
        $w1->setIsRestDay(false);
        $w1->setActivityType('WORKOUT');
        $plan->addWorkout($w1);

        $w2 = new Workout();
        $w2->setName('Spacer');
        $w2->setDayNumber(2);
        $w2->setIsRestDay(true);
        $w2->setActivityType('WALK');
        $plan->addWorkout($w2);

        $w3 = new Workout();
        $w3->setName('Pełny odpoczynek');
        $w3->setDayNumber(3);
        $w3->setIsRestDay(true);
        $w3->setActivityType('FULL_REST');
        $plan->addWorkout($w3);

        $w4 = new Workout();
        $w4->setName('Trening B');
        $w4->setDayNumber(4);
        $w4->setIsRestDay(false);
        $w4->setActivityType('WORKOUT');
        $plan->addWorkout($w4);

        $w5 = new Workout();
        $w5->setName('Rower');
        $w5->setDayNumber(5);
        $w5->setIsRestDay(true);
        $w5->setActivityType('CYCLING');
        $plan->addWorkout($w5);

        $w6 = new Workout();
        $w6->setName('Pełny odpoczynek');
        $w6->setDayNumber(6);
        $w6->setIsRestDay(true);
        $w6->setActivityType('FULL_REST');
        $plan->addWorkout($w6);

        $w7 = new Workout();
        $w7->setName('Pełny odpoczynek');
        $w7->setDayNumber(7);
        $w7->setIsRestDay(true);
        $w7->setActivityType('FULL_REST');
        $plan->addWorkout($w7);

        $analysis = $this->service->getCycleAnalysis($plan);

        $this->assertEquals(7, $analysis['cycleDays']);
        $this->assertEquals(2, $analysis['workoutDaysCount']); // Dni 1 i 4
        $this->assertEquals(5, $analysis['restDaysCount']); // Dni 2, 3, 5, 6, 7
        $this->assertEquals(2, $analysis['activeRecoveryDaysCount']); // Dni 2 i 5

        // Sprawdzenie interwałów przerw między treningami
        $this->assertCount(2, $analysis['intervals']);

        // Od Dnia 1 do Dnia 4: 2 dni przerwy (Dzień 2 i 3)
        $interval1 = $analysis['intervals'][0];
        $this->assertEquals(1, $interval1['fromDay']);
        $this->assertEquals(4, $interval1['toDay']);
        $this->assertEquals(2, $interval1['restDaysCount']);
        $this->assertCount(2, $interval1['activities']);
        $this->assertEquals('WALK', $interval1['activities'][0]['activityType']);
        $this->assertEquals('FULL_REST', $interval1['activities'][1]['activityType']);

        // Od Dnia 4 do Dnia 1 kolejnego cyklu: 3 dni przerwy (Dzień 5, 6, 7)
        $interval2 = $analysis['intervals'][1];
        $this->assertEquals(4, $interval2['fromDay']);
        $this->assertEquals(1, $interval2['toDay']);
        $this->assertEquals(3, $interval2['restDaysCount']);
        $this->assertCount(3, $interval2['activities']);
        $this->assertEquals('CYCLING', $interval2['activities'][0]['activityType']);
        $this->assertEquals('FULL_REST', $interval2['activities'][1]['activityType']);
        $this->assertEquals('FULL_REST', $interval2['activities'][2]['activityType']);

        $this->assertStringContainsString('Pomiędzy treningami zaplanowano min. 2 dni odpoczynku', $analysis['recommendation']);
    }

    public function testTrainingPlanCycleAnalyzerDirectly(): void
    {
        $analyzer = new \App\Service\TrainingPlan\TrainingPlanCycleAnalyzer();

        $plan = new TrainingPlan();
        $plan->setCycleDays(3);

        $w1 = new Workout();
        $w1->setDayNumber(1);
        $w1->setIsRestDay(false);
        $w1->setActivityType('WORKOUT');
        $plan->addWorkout($w1);

        $w2 = new Workout();
        $w2->setDayNumber(2);
        $w2->setIsRestDay(true);
        $w2->setActivityType('FULL_REST');
        $plan->addWorkout($w2);

        $w3 = new Workout();
        $w3->setDayNumber(3);
        $w3->setIsRestDay(true);
        $w3->setActivityType('FULL_REST');
        $plan->addWorkout($w3);

        $analysis = $analyzer->analyzeCycle($plan);

        $this->assertEquals(3, $analysis['cycleDays']);
        $this->assertEquals(1, $analysis['workoutDaysCount']);
        $this->assertEquals(2, $analysis['restDaysCount']);
        $this->assertCount(1, $analysis['intervals']);
        $this->assertEquals(2, $analysis['intervals'][0]['restDaysCount']);
    }

    private function setUserId(User $user, int $id): void
    {
        $ref = new \ReflectionClass(User::class);
        $prop = $ref->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($user, $id);
    }
}
