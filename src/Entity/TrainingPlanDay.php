<?php

namespace App\Entity;

use App\Repository\TrainingPlanDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TrainingPlanDayRepository::class)]
#[ORM\Table(name: 'training_plan_day')]
class TrainingPlanDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TrainingPlan::class, inversedBy: 'days')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrainingPlan $trainingPlan = null;

    #[ORM\Column]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private int $dayNumber = 1;

    #[ORM\Column(length: 255)]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private bool $isRestDay = false;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private ?string $activityType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private ?int $plannedDurationMinutes = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['plan:read', 'plan:read:full', 'workout:read', 'workout:read:full'])]
    private ?float $plannedDistanceKm = null;

    #[ORM\ManyToOne(targetEntity: WorkoutTemplate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['plan:read:full', 'workout:read:full', 'template:read'])]
    private ?WorkoutTemplate $template = null;

    /**
     * @var Collection<int, TrainingPlanDayExercise>
     */
    #[ORM\OneToMany(targetEntity: TrainingPlanDayExercise::class, mappedBy: 'planDay', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['plan:read:full'])]
    private Collection $exercises;

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getTrainingPlan(): ?TrainingPlan
    {
        return $this->trainingPlan;
    }

    public function setTrainingPlan(?TrainingPlan $trainingPlan): static
    {
        $this->trainingPlan = $trainingPlan;
        return $this;
    }

    public function getDayNumber(): int
    {
        return $this->dayNumber;
    }

    public function setDayNumber(int $dayNumber): static
    {
        $this->dayNumber = $dayNumber;
        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isRestDay(): bool
    {
        return $this->isRestDay;
    }

    public function setIsRestDay(bool $isRestDay): static
    {
        $this->isRestDay = $isRestDay;
        return $this;
    }

    public function getActivityType(): ?string
    {
        return $this->activityType;
    }

    public function setActivityType(?string $activityType): static
    {
        $this->activityType = $activityType;
        return $this;
    }

    public function getPlannedDurationMinutes(): ?int
    {
        return $this->plannedDurationMinutes;
    }

    public function setPlannedDurationMinutes(?int $plannedDurationMinutes): static
    {
        $this->plannedDurationMinutes = $plannedDurationMinutes;
        return $this;
    }

    public function getPlannedDistanceKm(): ?float
    {
        return $this->plannedDistanceKm;
    }

    public function setPlannedDistanceKm(?float $plannedDistanceKm): static
    {
        $this->plannedDistanceKm = $plannedDistanceKm;
        return $this;
    }

    public function getTemplate(): ?WorkoutTemplate
    {
        return $this->template;
    }

    public function setTemplate(?WorkoutTemplate $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return Collection<int, TrainingPlanDayExercise>
     */
    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    public function addExercise(TrainingPlanDayExercise $exercise): static
    {
        if (!$this->exercises->contains($exercise)) {
            $this->exercises->add($exercise);
            $exercise->setPlanDay($this);
        }
        return $this;
    }

    public function removeExercise(TrainingPlanDayExercise $exercise): static
    {
        if ($this->exercises->removeElement($exercise)) {
            if ($exercise->getPlanDay() === $this) {
                $exercise->setPlanDay(null);
            }
        }
        return $this;
    }
}
