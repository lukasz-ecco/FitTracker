<?php

namespace App\Tests\Service;

use App\Entity\Exercises;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Entity\WorkoutExerciseSet;
use App\Repository\WorkoutRepository;
use App\Service\TrainingPlan\TrainingPlanSessionLauncher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TrainingPlanSessionLauncherTest extends TestCase
{
    private $em;
    private $workoutRepo;
    private TrainingPlanSessionLauncher $launcher;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->workoutRepo = $this->createMock(WorkoutRepository::class);

        $this->launcher = new TrainingPlanSessionLauncher(
            $this->em,
            $this->workoutRepo
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setValue($entity, $id);
    }

    public function testStartWorkoutFromPlanClonesExercisesAndSets(): void
    {
        $user = new User();
        $this->setEntityId($user, 1);

        $plan = new TrainingPlan();
        $this->setEntityId($plan, 10);
        $plan->setUser($user);

        $planWorkout = new Workout();
        $this->setEntityId($planWorkout, 101);
        $planWorkout->setName('FBW A');
        $planWorkout->setDescription('Opis sesji');
        $planWorkout->setTrainingPlan($plan);
        $planWorkout->setDayNumber(1);
        $planWorkout->setStatus('PLANNED');

        $exercise = new Exercises();
        $this->setEntityId($exercise, 5);
        $exercise->setName('Wyciskanie sztangi');

        $we = new WorkoutExercise();
        $we->setExercise($exercise);
        $we->setOrderIndex(1);
        $we->setNotes('Technika 3-1-X-1');

        $s1 = new WorkoutExerciseSet();
        $s1->setSetNumber(1);
        $s1->setReps(10);
        $s1->setWeight(80.0);
        $s1->setIsCompleted(true);

        $we->addWorkoutExerciseSet($s1);
        $planWorkout->addWorkoutExercise($we);

        $this->workoutRepo->method('find')->with(101)->willReturn($planWorkout);

        $session = $this->launcher->startWorkoutFromPlan($user, 101);

        $this->assertEquals('FBW A', $session->getName());
        $this->assertEquals('Opis sesji', $session->getDescription());
        $this->assertEquals('IN_PROGRESS', $session->getStatus());
        $this->assertEquals(1, $session->getDayNumber());
        $this->assertSame($plan, $session->getTrainingPlan());
        $this->assertCount(1, $session->getWorkoutExercises());

        $clonedWe = $session->getWorkoutExercises()[0];
        $this->assertSame($exercise, $clonedWe->getExercise());
        $this->assertEquals('Technika 3-1-X-1', $clonedWe->getNotes());
        $this->assertCount(1, $clonedWe->getWorkoutExerciseSets());

        $clonedSet = $clonedWe->getWorkoutExerciseSets()[0];
        $this->assertEquals(1, $clonedSet->getSetNumber());
        $this->assertEquals(10, $clonedSet->getReps());
        $this->assertEquals(80.0, $clonedSet->getWeight());
        $this->assertFalse($clonedSet->isCompleted());
    }

    public function testStartWorkoutThrowsNotFoundWhenWorkoutDoesNotExist(): void
    {
        $user = new User();
        $this->setEntityId($user, 1);

        $this->workoutRepo->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->launcher->startWorkoutFromPlan($user, 999);
    }

    public function testStartWorkoutThrowsAccessDeniedWhenPlanNotOwned(): void
    {
        $owner = new User();
        $this->setEntityId($owner, 1);

        $stranger = new User();
        $this->setEntityId($stranger, 2);

        $plan = new TrainingPlan();
        $this->setEntityId($plan, 10);
        $plan->setUser($owner);

        $planWorkout = new Workout();
        $this->setEntityId($planWorkout, 101);
        $planWorkout->setTrainingPlan($plan);

        $this->workoutRepo->method('find')->with(101)->willReturn($planWorkout);

        $this->expectException(AccessDeniedException::class);
        $this->launcher->startWorkoutFromPlan($stranger, 101);
    }
}
