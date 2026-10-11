<?php

namespace App\Entity;

use App\Repository\TrainingPlanDayExerciseSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TrainingPlanDayExerciseSetRepository::class)]
#[ORM\Table(name: 'training_plan_day_exercise_set')]
class TrainingPlanDayExerciseSet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plan:read:full', 'workout:read:full', 'set:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TrainingPlanDayExercise::class, inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrainingPlanDayExercise $planDayExercise = null;

    #[ORM\Column]
    #[Groups(['plan:read:full', 'workout:read:full', 'set:read'])]
    private ?int $setNumber = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['plan:read:full', 'workout:read:full', 'set:read'])]
    private ?int $reps = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['plan:read:full', 'workout:read:full', 'set:read'])]
    private ?float $weight = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['plan:read:full', 'workout:read:full', 'set:read'])]
    private ?string $tempo = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['plan:read:full', 'workout:read:full', 'set:read'])]
    private bool $isDropSet = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanDayExercise(): ?TrainingPlanDayExercise
    {
        return $this->planDayExercise;
    }

    public function setPlanDayExercise(?TrainingPlanDayExercise $planDayExercise): static
    {
        $this->planDayExercise = $planDayExercise;
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

    public function isDropSet(): bool
    {
        return $this->isDropSet;
    }

    public function setIsDropSet(bool $isDropSet): static
    {
        $this->isDropSet = $isDropSet;
        return $this;
    }
}
