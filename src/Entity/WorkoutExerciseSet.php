<?php

namespace App\Entity;

use App\Repository\WorkoutExerciseSetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: WorkoutExerciseSetRepository::class)]
class WorkoutExerciseSet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'workoutExerciseSets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkoutExercise $workoutExercise = null;

    #[ORM\Column]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?int $setNumber = null;

    #[ORM\Column]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?int $reps = null;

    #[ORM\Column]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?float $weight = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?string $tempo = null;

    #[ORM\Column]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?bool $isCompleted = false;

    #[ORM\Column]
    #[Groups(['workout:read:full', 'set:read'])]
    private ?bool $isDropSet = false;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[Groups(['workout:read:full'])]
    private ?self $parentSet = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkoutExercise(): ?WorkoutExercise
    {
        return $this->workoutExercise;
    }

    public function setWorkoutExercise(?WorkoutExercise $workoutExercise): static
    {
        $this->workoutExercise = $workoutExercise;
        return $this;
    }

    public function getSetNumber(): ?int
    {
        return $this->setNumber;
    }

    public function setSetNumber(int $setNumber): static
    {
        $this->setNumber = $setNumber;
        return $this;
    }

    public function getReps(): ?int
    {
        return $this->reps;
    }

    public function setReps(int $reps): static
    {
        $this->reps = $reps;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getTempo(): ?string
    {
        return $this->tempo;
    }

    public function setTempo(?string $tempo): static
    {
        $this->tempo = $tempo;
        return $this;
    }

    public function isCompleted(): ?bool
    {
        return $this->isCompleted;
    }

    public function setCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        return $this;
    }

    public function isDropSet(): ?bool
    {
        return $this->isDropSet;
    }

    public function setDropSet(bool $isDropSet): static
    {
        $this->isDropSet = $isDropSet;
        return $this;
    }

    public function getParentSet(): ?self
    {
        return $this->parentSet;
    }

    public function setParentSet(?self $parentSet): static
    {
        $this->parentSet = $parentSet;
        return $this;
    }
}
