<?php

namespace App\Entity;

use App\Repository\WorkoutExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: WorkoutExerciseRepository::class)]
class WorkoutExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['workout:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'workoutExercises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Workout $workout = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['workout:read:full'])]
    private ?Exercises $exercise = null;

    #[ORM\Column]
    #[Groups(['workout:read:full'])]
    private ?int $orderIndex = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['workout:read:full'])]
    private ?string $notes = null;

    /**
     * @var Collection<int, WorkoutExerciseSet>
     */
    #[ORM\OneToMany(targetEntity: WorkoutExerciseSet::class, mappedBy: 'workoutExercise', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Groups(['workout:read:full'])]
    private Collection $workoutExerciseSets;

    public function __construct()
    {
        $this->workoutExerciseSets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkout(): ?Workout
    {
        return $this->workout;
    }

    public function setWorkout(?Workout $workout): static
    {
        $this->workout = $workout;
        return $this;
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

    public function getOrderIndex(): ?int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * @return Collection<int, WorkoutExerciseSet>
     */
    public function getWorkoutExerciseSets(): Collection
    {
        return $this->workoutExerciseSets;
    }

    public function addWorkoutExerciseSet(WorkoutExerciseSet $workoutExerciseSet): static
    {
        if (!$this->workoutExerciseSets->contains($workoutExerciseSet)) {
            $this->workoutExerciseSets->add($workoutExerciseSet);
            $workoutExerciseSet->setWorkoutExercise($this);
        }

        return $this;
    }

    public function removeWorkoutExerciseSet(WorkoutExerciseSet $workoutExerciseSet): static
    {
        if ($this->workoutExerciseSets->removeElement($workoutExerciseSet)) {
            // set the owning side to null (unless already changed)
            if ($workoutExerciseSet->getWorkoutExercise() === $this) {
                $workoutExerciseSet->setWorkoutExercise(null);
            }
        }

        return $this;
    }
}
