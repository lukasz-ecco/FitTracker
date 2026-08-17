<?php

namespace App\Entity;

use App\Entity\GoalType;
use App\Repository\TrainingGoalRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TrainingGoalRepository::class)]
class TrainingGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goal:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trainingGoals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: GoalType::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['goal:read'])]
    private ?GoalType $goalType = null;

    #[ORM\Column(type: 'smallint')]
    #[Groups(['goal:read'])]
    private ?int $fitnessLevel = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['goal:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Groups(['goal:read'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['goal:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getGoalType(): ?GoalType
    {
        return $this->goalType;
    }

    public function setGoalType(GoalType $goalType): static
    {
        $this->goalType = $goalType;

        return $this;
    }

    public function getFitnessLevel(): ?int
    {
        return $this->fitnessLevel;
    }

    public function setFitnessLevel(int $fitnessLevel): static
    {
        $this->fitnessLevel = $fitnessLevel;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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
}
