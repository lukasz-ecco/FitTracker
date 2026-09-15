<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Service\WorkoutService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class WorkoutServiceTest extends TestCase
{
    private $em;
    private $validator;
    private $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        $this->service = new WorkoutService($this->em, $this->validator);
    }

    public function testUpdateWorkoutStatusAndMetrics(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        // Używamy refleksji do ustawienia ID, jako że User normalnie ma generowane
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('DRAFT');

        $data = [
            'status' => 'COMPLETED',
            'duration' => 45,
            'volume' => 2500.5
        ];

        $updatedWorkout = $this->service->updateWorkout($workout, $user, $data);

        $this->assertEquals('COMPLETED', $updatedWorkout->getStatus());
        $this->assertEquals(45, $updatedWorkout->getDuration());
        $this->assertEquals(2500.5, $updatedWorkout->getVolume());
    }

    public function testDirectTransitionFromDraftToInProgress(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('DRAFT');

        $data = [
            'status' => 'IN_PROGRESS'
        ];

        $updatedWorkout = $this->service->updateWorkout($workout, $user, $data);

        $this->assertEquals('IN_PROGRESS', $updatedWorkout->getStatus());
    }

    public function testTransitionFromPlannedToInProgress(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('PLANNED');

        $data = [
            'status' => 'IN_PROGRESS'
        ];

        $updatedWorkout = $this->service->updateWorkout($workout, $user, $data);

        $this->assertEquals('IN_PROGRESS', $updatedWorkout->getStatus());
    }

    public function testCompleteWorkoutWithDurationAndVolume(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('IN_PROGRESS');

        $data = [
            'status' => 'COMPLETED',
            'duration' => 60,
            'volume' => 4500.0,
        ];

        $updatedWorkout = $this->service->updateWorkout($workout, $user, $data);

        $this->assertEquals('COMPLETED', $updatedWorkout->getStatus());
        $this->assertEquals(60, $updatedWorkout->getDuration());
        $this->assertEquals(4500.0, $updatedWorkout->getVolume());
    }

    public function testCannotUpdateAlreadyCompletedWorkout(): void
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('COMPLETED');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nie można modyfikować zakończonego treningu.');

        $this->service->updateWorkout($workout, $user, ['name' => 'Nowa nazwa']);
    }

    public function testCannotAddExerciseToCompletedWorkout(): void
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('COMPLETED');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nie można modyfikować zakończonego treningu.');

        $this->service->addExerciseToWorkout($workout, $user, ['exerciseId' => 1]);
    }

    public function testCannotAddSetToCompletedWorkout(): void
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('COMPLETED');

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->setWorkout($workout);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nie można modyfikować zakończonego treningu.');

        $this->service->addSetToExercise($workoutExercise, $user, ['reps' => 10, 'weight' => 50.0]);
    }

    public function testCannotUpdateSetInCompletedWorkout(): void
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        $workout = new Workout();
        $workout->setUser($user);
        $workout->setStatus('COMPLETED');

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->setWorkout($workout);

        $set = new WorkoutExerciseSet();
        $set->setWorkoutExercise($workoutExercise);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nie można modyfikować zakończonego treningu.');

        $this->service->updateSet($set, $user, ['reps' => 12]);
    }
}
