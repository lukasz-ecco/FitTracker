<?php

namespace App\Entity;

use App\Repository\TrainingPlanDayExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TrainingPlanDayExerciseRepository::class)]
#[ORM\Table(name: 'training_plan_day_exercise')]
class TrainingPlanDayExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plan:read:full', 'workout:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TrainingPlanDay::class, inversedBy: 'exercises')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrainingPlanDay $planDay = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['plan:read:full', 'workout:read:full', 'exercise:read'])]
    private ?Exercises $exercise = null;

    #[ORM\Column]
    #[Groups(['plan:read:full', 'workout:read:full'])]
    private ?int $orderIndex = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan:read:full', 'workout:read:full'])]
    private ?string $notes = null;

    /**
     * @var Collection<int, TrainingPlanDayExerciseSet>
     */
    #[ORM\OneToMany(targetEntity: TrainingPlanDayExerciseSet::class, mappedBy: 'planDayExercise', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['setNumber' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['plan:read:full'])]
    private Collection $sets;

    public function __construct()
    {
        $this->sets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanDay(): ?TrainingPlanDay
    {
        return $this->planDay;
    }

    public function setPlanDay(?TrainingPlanDay $planDay): static
    {
        $this->planDay = $planDay;
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
     * @return Collection<int, TrainingPlanDayExerciseSet>
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    public function addSet(TrainingPlanDayExerciseSet $set): static
    {
        if (!$this->sets->contains($set)) {
            $this->sets->add($set);
            $set->setPlanDayExercise($this);
        }
        return $this;
    }

    public function removeSet(TrainingPlanDayExerciseSet $set): static
    {
        if ($this->sets->removeElement($set)) {
            if ($set->getPlanDayExercise() === $this) {
                $set->setPlanDayExercise(null);
            }
        }
        return $this;
    }
}
