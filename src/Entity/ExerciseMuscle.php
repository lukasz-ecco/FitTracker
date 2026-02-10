<?php

namespace App\Entity;

use App\Enum\MuscleActivationLevel;
use App\Repository\ExerciseMuscleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciseMuscleRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_exercise_muscle', columns: ['exercise_id', 'muscle_id'])]
class ExerciseMuscle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'exerciseMuscles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exercises $Exercise = null;

    #[ORM\ManyToOne(inversedBy: 'exerciseMuscles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Muscles $Muscle = null;

    #[ORM\Column(enumType: MuscleActivationLevel::class)]
    private ?MuscleActivationLevel $ActivationLevel = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExercise(): ?Exercises
    {
        return $this->Exercise;
    }

    public function setExercise(?Exercises $Exercise): static
    {
        $this->Exercise = $Exercise;

        return $this;
    }

    public function getMuscle(): ?Muscles
    {
        return $this->Muscle;
    }

    public function setMuscle(?Muscles $Muscle): static
    {
        $this->Muscle = $Muscle;

        return $this;
    }

    public function getActivationLevel(): ?MuscleActivationLevel
    {
        return $this->ActivationLevel;
    }

    public function setActivationLevel(MuscleActivationLevel $ActivationLevel): static
    {
        $this->ActivationLevel = $ActivationLevel;

        return $this;
    }
}
