<?php

namespace App\Entity;

use App\Repository\ExercisesRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

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

    public function __construct()
    {
        $this->exerciseMuscles = new ArrayCollection();
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
}
