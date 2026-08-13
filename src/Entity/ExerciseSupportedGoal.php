<?php

namespace App\Entity;

use App\Entity\GoalType;
use App\Repository\ExerciseSupportedGoalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciseSupportedGoalRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_exercise_goal', columns: ['exercise_id', 'goal_type_id'])]
class ExerciseSupportedGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Exercises::class, inversedBy: 'supportedGoals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exercises $exercise = null;

    #[ORM\ManyToOne(targetEntity: GoalType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?GoalType $goalType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExercise(): ?Exercises
    {
        return $this->exercise;
    }

    public function setExercise(?Exercises $exercise): static
    {
        $this->exercise = $exercise;

        return $this;
    }

    public function getGoalType(): ?GoalType
    {
        return $this->goalType;
    }

    public function setGoalType(GoalType $goalType): static
    {
        $this->goalType = $goalType;

        return $this;
    }
}
