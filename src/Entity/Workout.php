<?php

namespace App\Entity;

use App\Repository\WorkoutRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: WorkoutRepository::class)]
class Workout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?string $status = 'DRAFT';

    #[ORM\Column(nullable: true)]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?int $duration = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?float $volume = null;


    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?User $trainer = null;

    #[ORM\ManyToOne(targetEntity: TrainingPlan::class, inversedBy: 'workouts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Ignore]
    private ?TrainingPlan $trainingPlan = null;

    #[ORM\ManyToOne(targetEntity: WorkoutTemplate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['workout:read', 'workout:read:full'])]
    private ?WorkoutTemplate $template = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['workout:read', 'workout:read:full', 'plan:read:full'])]
    private ?int $dayNumber = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['workout:read', 'workout:read:full', 'plan:read:full'])]
    private bool $isRestDay = false;

    /**
     * @var Collection<int, WorkoutExercise>
     */
    #[ORM\OneToMany(targetEntity: WorkoutExercise::class, mappedBy: 'workout', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Groups(['workout:read:full'])]
    private Collection $workoutExercises;

    public function __construct()
    {
        $this->workoutExercises = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getVolume(): ?float
    {
        return $this->volume;
    }

    public function setVolume(?float $volume): static
    {
        $this->volume = $volume;
        return $this;
    }


    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTrainer(): ?User
    {
        return $this->trainer;
    }

    public function setTrainer(?User $trainer): static
    {
        $this->trainer = $trainer;
        return $this;
    }

    /**
     * @return Collection<int, WorkoutExercise>
     */
    public function getWorkoutExercises(): Collection
    {
        return $this->workoutExercises;
    }

    public function addWorkoutExercise(WorkoutExercise $workoutExercise): static
    {
        if (!$this->workoutExercises->contains($workoutExercise)) {
            $this->workoutExercises->add($workoutExercise);
            $workoutExercise->setWorkout($this);
        }

        return $this;
    }

    public function removeWorkoutExercise(WorkoutExercise $workoutExercise): static
    {
        if ($this->workoutExercises->removeElement($workoutExercise)) {
            // set the owning side to null (unless already changed)
            if ($workoutExercise->getWorkout() === $this) {
                $workoutExercise->setWorkout(null);
            }
        }

        return $this;
    }

    #[Ignore]
    public function getTrainingPlan(): ?TrainingPlan
    {
        return $this->trainingPlan;
    }

    #[Groups(['workout:read', 'workout:read:full'])]
    public function getTrainingPlanId(): ?int
    {
        return $this->trainingPlan?->getId();
    }

    #[Groups(['workout:read', 'workout:read:full'])]
    public function getTrainingPlanName(): ?string
    {
        return $this->trainingPlan?->getName();
    }

    public function setTrainingPlan(?TrainingPlan $trainingPlan): static
    {
        $this->trainingPlan = $trainingPlan;
        return $this;
    }

    public function getDayNumber(): ?int
    {
        return $this->dayNumber;
    }

    public function setDayNumber(?int $dayNumber): static
    {
        $this->dayNumber = $dayNumber;
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

    public function getTemplate(): ?WorkoutTemplate
    {
        return $this->template;
    }

    public function setTemplate(?WorkoutTemplate $template): static
    {
        $this->template = $template;
        return $this;
    }
}
