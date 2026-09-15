<?php

namespace App\Entity;

use App\Repository\WorkoutTemplateExerciseSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: WorkoutTemplateExerciseSetRepository::class)]
class WorkoutTemplateExerciseSet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['template:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkoutTemplateExercise $templateExercise = null;

    #[ORM\Column]
    #[Groups(['template:read:full'])]
    private ?int $setNumber = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['template:read:full'])]
    private ?int $reps = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['template:read:full'])]
    private ?float $weight = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['template:read:full'])]
    private ?string $tempo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateExercise(): ?WorkoutTemplateExercise
    {
        return $this->templateExercise;
    }

    public function setTemplateExercise(?WorkoutTemplateExercise $templateExercise): static
    {
        $this->templateExercise = $templateExercise;
        return $this;
    }

    public function getSetNumber(): ?int
    {
        return $this->setNumber;
    }

    public function setSetNumber(int $setNumber): static
    {
        $this->setNumber = $setNumber;
        return $this;
    }

    public function getReps(): ?int
    {
        return $this->reps;
    }

    public function setReps(?int $reps): static
    {
        $this->reps = $reps;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getTempo(): ?string
    {
        return $this->tempo;
    }

    public function setTempo(?string $tempo): static
    {
        $this->tempo = $tempo;
        return $this;
    }
}
