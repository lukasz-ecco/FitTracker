<?php

namespace App\Entity;

use App\Repository\ExercisesRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Enum\TrainingGoalType;

#[ORM\Entity(repositoryClass: ExercisesRepository::class)]
class Exercises
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $difficulty = null;

    #[ORM\Column(length: 150)]
    private ?string $type = null;

    /**
     * @var Collection<int, ExerciseMuscle>
     */
    #[ORM\OneToMany(targetEntity: ExerciseMuscle::class, mappedBy: 'Exercise', cascade: ['persist'], orphanRemoval: true)]
    private Collection $exerciseMuscles;

    /**
     * @var Collection<int, ExerciseSupportedGoal>
     */
    #[ORM\OneToMany(targetEntity: ExerciseSupportedGoal::class, mappedBy: 'exercise', cascade: ['persist'], orphanRemoval: true)]
    private Collection $supportedGoals;

    public function __construct()
    {
        $this->exerciseMuscles = new ArrayCollection();
        $this->supportedGoals = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDifficulty(): ?int
    {
        return $this->difficulty;
    }

    public function setDifficulty(int $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, ExerciseMuscle>
     */
    public function getExerciseMuscles(): Collection
    {
        return $this->exerciseMuscles;
    }

    public function addExerciseMuscle(ExerciseMuscle $exerciseMuscle): static
    {
        if (!$this->exerciseMuscles->contains($exerciseMuscle)) {
            $this->exerciseMuscles->add($exerciseMuscle);
            $exerciseMuscle->setExercise($this);
        }

        return $this;
    }

    public function removeExerciseMuscle(ExerciseMuscle $exerciseMuscle): static
    {
        if ($this->exerciseMuscles->removeElement($exerciseMuscle)) {
            // set the owning side to null (unless already changed)
            if ($exerciseMuscle->getExercise() === $this) {
                $exerciseMuscle->setExercise(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ExerciseSupportedGoal>
     */
    public function getSupportedGoals(): Collection
    {
        return $this->supportedGoals;
    }

    public function addSupportedGoal(ExerciseSupportedGoal $supportedGoal): static
    {
        if (!$this->supportedGoals->contains($supportedGoal)) {
            $this->supportedGoals->add($supportedGoal);
            $supportedGoal->setExercise($this);
        }

        return $this;
    }

    public function removeSupportedGoal(ExerciseSupportedGoal $supportedGoal): static
    {
        if ($this->supportedGoals->removeElement($supportedGoal)) {
            // set the owning side to null (unless already changed)
            if ($supportedGoal->getExercise() === $this) {
                $supportedGoal->setExercise(null);
            }
        }

        return $this;
    }
}
