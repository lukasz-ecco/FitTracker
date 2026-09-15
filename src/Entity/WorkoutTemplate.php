<?php

namespace App\Entity;

use App\Repository\WorkoutTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: WorkoutTemplateRepository::class)]
class WorkoutTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['template:read', 'template:read:full'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['template:read', 'template:read:full'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['template:read', 'template:read:full'])]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['template:read', 'template:read:full'])]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['template:read', 'template:read:full'])]
    private ?User $trainer = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['template:read', 'template:read:full'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, WorkoutTemplateExercise>
     */
    #[ORM\OneToMany(targetEntity: WorkoutTemplateExercise::class, mappedBy: 'template', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    #[Groups(['template:read:full'])]
    private Collection $exercises;

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getTrainer(): ?User
    {
        return $this->trainer;
    }

    public function setTrainer(?User $trainer): static
    {
        $this->trainer = $trainer;
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
     * @return Collection<int, WorkoutTemplateExercise>
     */
    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    public function addExercise(WorkoutTemplateExercise $exercise): static
    {
        if (!$this->exercises->contains($exercise)) {
            $this->exercises->add($exercise);
            $exercise->setTemplate($this);
        }
        return $this;
    }

    public function removeExercise(WorkoutTemplateExercise $exercise): static
    {
        if ($this->exercises->removeElement($exercise)) {
            if ($exercise->getTemplate() === $this) {
                $exercise->setTemplate(null);
            }
        }
        return $this;
    }

    #[Groups(['template:read', 'template:read:full'])]
    public function getExercisesCount(): int
    {
        return $this->exercises->count();
    }
}
