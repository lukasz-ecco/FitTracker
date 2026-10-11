<?php

namespace App\Entity;

use App\Repository\TrainingPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TrainingPlanRepository::class)]
#[ORM\Table(name: 'training_plan')]
class TrainingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plan:read', 'plan:read:full'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['plan:read', 'plan:read:full'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan:read', 'plan:read:full'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['plan:read', 'plan:read:full'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['plan:read', 'plan:read:full'])]
    private ?User $creator = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['plan:read', 'plan:read:full'])]
    private bool $isActive = false;

    #[ORM\Column(options: ['default' => 7])]
    #[Groups(['plan:read', 'plan:read:full'])]
    private int $cycleDays = 7;

    #[ORM\Column]
    #[Groups(['plan:read', 'plan:read:full'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, TrainingPlanDay>
     */
    #[ORM\OneToMany(targetEntity: TrainingPlanDay::class, mappedBy: 'trainingPlan', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['dayNumber' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['plan:read:full'])]
    private Collection $days;

    /**
     * @var Collection<int, Workout>
     */
    #[ORM\OneToMany(targetEntity: Workout::class, mappedBy: 'trainingPlan')]
    #[ORM\OrderBy(['date' => 'DESC', 'id' => 'DESC'])]
    private Collection $executedWorkouts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->days = new ArrayCollection();
        $this->executedWorkouts = new ArrayCollection();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCycleDays(): int
    {
        return $this->cycleDays;
    }

    public function setCycleDays(int $cycleDays): static
    {
        $this->cycleDays = $cycleDays;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, TrainingPlanDay>
     */
    public function getDays(): Collection
    {
        return $this->days;
    }

    public function addDay(TrainingPlanDay $day): static
    {
        if (!$this->days->contains($day)) {
            $this->days->add($day);
            $day->setTrainingPlan($this);
        }

        return $this;
    }

    public function removeDay(TrainingPlanDay $day): static
    {
        if ($this->days->removeElement($day)) {
            if ($day->getTrainingPlan() === $this) {
                $day->setTrainingPlan(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Workout>
     */
    public function getExecutedWorkouts(): Collection
    {
        return $this->executedWorkouts;
    }
}
