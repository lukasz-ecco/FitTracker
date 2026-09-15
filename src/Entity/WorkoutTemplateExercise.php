<?php

namespace App\Entity;

use App\Repository\WorkoutTemplateExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: WorkoutTemplateExerciseRepository::class)]
class WorkoutTemplateExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['template:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'exercises')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkoutTemplate $template = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['template:read:full'])]
    private ?Exercises $exercise = null;

    #[ORM\Column]
    #[Groups(['template:read:full'])]
    private ?int $orderIndex = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['template:read:full'])]
    private ?string $notes = null;

    /**
     * @var Collection<int, WorkoutTemplateExerciseSet>
     */
    #[ORM\OneToMany(targetEntity: WorkoutTemplateExerciseSet::class, mappedBy: 'templateExercise', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['setNumber' => 'ASC'])]
    #[Groups(['template:read:full'])]
    private Collection $sets;

    public function __construct()
    {
        $this->sets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection<int, WorkoutTemplateExerciseSet>
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    public function addSet(WorkoutTemplateExerciseSet $set): static
    {
        if (!$this->sets->contains($set)) {
            $this->sets->add($set);
            $set->setTemplateExercise($this);
        }
        return $this;
    }

    public function removeSet(WorkoutTemplateExerciseSet $set): static
    {
        if ($this->sets->removeElement($set)) {
            if ($set->getTemplateExercise() === $this) {
                $set->setTemplateExercise(null);
            }
        }
        return $this;
    }
}
