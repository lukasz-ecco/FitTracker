<?php

namespace App\Entity;

use App\Repository\TrainerTraineeConnectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TrainerTraineeConnectionRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_trainer_trainee', columns: ['trainer_id', 'trainee_id'])]
class TrainerTraineeConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['connection:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'trainerConnections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['connection:read'])]
    private ?User $trainer = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'traineeConnections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['connection:read'])]
    private ?User $trainee = null;

    #[ORM\Column]
    #[Groups(['connection:read'])]
    private bool $isMain = false;

    /**
     * Zastępuje enum, wartości np: PENDING, ACCEPTED, REJECTED
     */
    #[ORM\Column(length: 20)]
    #[Groups(['connection:read'])]
    private ?string $status = 'PENDING';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['connection:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTrainee(): ?User
    {
        return $this->trainee;
    }

    public function setTrainee(?User $trainee): static
    {
        $this->trainee = $trainee;

        return $this;
    }

    public function isMain(): bool
    {
        return $this->isMain;
    }

    public function setIsMain(bool $isMain): static
    {
        $this->isMain = $isMain;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
