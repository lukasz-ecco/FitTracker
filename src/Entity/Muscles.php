<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\MusclesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MusclesRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection()
    ]
)]
class Muscles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'muscles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BodyParts $bodyPart = null;

    /**
     * @var Collection<int, ExerciseMuscle>
     */
    #[ORM\OneToMany(targetEntity: ExerciseMuscle::class, mappedBy: 'Muscle')]
    private Collection $exerciseMuscles;

    public function __construct()
    {
        $this->exerciseMuscles = new ArrayCollection();
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

    public function getBodyPart(): ?BodyParts
    {
        return $this->bodyPart;
    }

    public function setBodyPart(?BodyParts $bodyPart): static
    {
        $this->bodyPart = $bodyPart;

        return $this;
    }

    /**
     * @return Collection<int, ExerciseMuscle>
     */
    public function getExerciseMuscles(): Collection
    {
        return $this->exerciseMuscles;
    }

    public function addExerciseMuscle(ExerciseMuscle $exerciseMuscle): static
    {
        if (!$this->exerciseMuscles->contains($exerciseMuscle)) {
            $this->exerciseMuscles->add($exerciseMuscle);
            $exerciseMuscle->setMuscle($this);
        }

        return $this;
    }

    public function removeExerciseMuscle(ExerciseMuscle $exerciseMuscle): static
    {
        if ($this->exerciseMuscles->removeElement($exerciseMuscle)) {
            if ($exerciseMuscle->getMuscle() === $this) {
                $exerciseMuscle->setMuscle(null);
            }
        }

        return $this;
    }
}
