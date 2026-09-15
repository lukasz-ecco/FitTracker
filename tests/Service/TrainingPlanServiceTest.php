<?php

namespace App\Tests\Service;

use App\Entity\TrainerTraineeConnection;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Service\TrainingPlanService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TrainingPlanServiceTest extends TestCase
{
    private $em;
    private $validator;
    private $connectionRepo;
    private $userRepo;
    private $workoutRepo;
    private $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->connectionRepo = $this->createMock(TrainerTraineeConnectionRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->workoutRepo = $this->createMock(WorkoutRepository::class);
        $this->planRepo = $this->createMock(\App\Repository\TrainingPlanRepository::class);

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->service = new TrainingPlanService(
            $this->em,
            $this->validator,
            $this->connectionRepo,
            $this->userRepo,
            $this->workoutRepo,
            $this->planRepo
        );
    }

    public function testCreatePlanForSelf(): void
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $this->setUserId($user, 1);

        $plan = $this->service->createPlan($user, [
            'name' => 'Mój plan FBW',
            'description' => '3 dni w tygodniu'
        ]);

        $this->assertEquals('Mój plan FBW', $plan->getName());
        $this->assertEquals('3 dni w tygodniu', $plan->getDescription());
        $this->assertSame($user, $plan->getUser());
        $this->assertSame($user, $plan->getCreator());
        $this->assertFalse($plan->isActive());
    }

    public function testCreatePlanWithWorkoutsAndRestDay(): void
    {
        $user = new User();
        $this->setUserId($user, 1);

        $plan = $this->service->createPlan($user, [
            'name' => 'Plan PPL',
            'workouts' => [
                [
                    'name' => 'Push A',
                    'dayNumber' => 1,
                    'isRestDay' => false,
                    'notes' => 'Ciężko na klatkę'
                ],
                [
                    'name' => 'Regeneracja',
                    'dayNumber' => 2,
                    'isRestDay' => true,
                    'notes' => '10 000 kroków'
                ]
            ]
        ]);

        $workouts = $plan->getWorkouts();
        $this->assertCount(2, $workouts);
        
        $day1 = $workouts[0];
        $this->assertEquals('Push A', $day1->getName());
        $this->assertEquals(1, $day1->getDayNumber());
        $this->assertFalse($day1->isRestDay());
        $this->assertEquals('Ciężko na klatkę', $day1->getDescription());

        $day2 = $workouts[1];
        $this->assertEquals('Regeneracja', $day2->getName());
        $this->assertEquals(2, $day2->getDayNumber());
        $this->assertTrue($day2->isRestDay());
        $this->assertEquals('10 000 kroków', $day2->getDescription());
    }

    public function testTrainerCanCreatePlanForAcceptedTrainee(): void
    {
        $trainer = new User();
        $trainer->setRoles(['ROLE_TRAINER']);
        $this->setUserId($trainer, 10);

        $trainee = new User();
        $trainee->setRoles(['ROLE_USER']);
        $this->setUserId($trainee, 20);

        $this->userRepo->method('find')->with(20)->willReturn($trainee);

        $connection = new TrainerTraineeConnection();
        $connection->setTrainer($trainer);
        $connection->setTrainee($trainee);
        $connection->setStatus('ACCEPTED');

        $this->connectionRepo->method('findAcceptedConnection')
            ->with($trainer, $trainee)
            ->willReturn($connection);

        $plan = $this->service->createPlan($trainer, [
            'name' => 'Plan dla Podopiecznego',
            'traineeId' => 20
        ]);

        $this->assertEquals('Plan dla Podopiecznego', $plan->getName());
        $this->assertSame($trainee, $plan->getUser());
        $this->assertSame($trainer, $plan->getCreator());
    }

    public function testTrainerCannotCreatePlanForNonTrainee(): void
    {
        $trainer = new User();
        $this->setUserId($trainer, 10);

        $randomUser = new User();
        $this->setUserId($randomUser, 99);

        $this->userRepo->method('find')->with(99)->willReturn($randomUser);
        $this->connectionRepo->method('findAcceptedConnection')
            ->with($trainer, $randomUser)
            ->willReturn(null);

        $this->expectException(AccessDeniedException::class);
        $this->service->createPlan($trainer, [
            'name' => 'Nielegalny plan',
            'traineeId' => 99
        ]);
    }

    public function testAddWorkoutToPlan(): void
    {
        $user = new User();
        $this->setUserId($user, 1);

        $plan = new TrainingPlan();
        $plan->setUser($user);
        $plan->setName('Plan testowy');

        $workout = $this->service->addWorkoutToPlan($plan, $user, [
            'name' => 'Legs Day',
            'dayNumber' => 3,
            'isRestDay' => false,
            'notes' => 'Przysiady 5x5'
        ]);

        $this->assertEquals('Legs Day', $workout->getName());
        $this->assertEquals(3, $workout->getDayNumber());
        $this->assertFalse($workout->isRestDay());
        $this->assertSame($plan, $workout->getTrainingPlan());
        $this->assertEquals('Przysiady 5x5', $workout->getDescription());
    }

    private function setUserId(User $user, int $id): void
    {
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, $id);
    }
}
