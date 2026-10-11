<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\BodyPartsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BodyPartsRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['measurement:read']],
    order: ['sortOrder' => 'ASC', 'name' => 'ASC']
)]
class BodyParts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['measurement:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['measurement:read'])]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => 100])]
    #[Groups(['measurement:read'])]
    private int $sortOrder = 100;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['measurement:read'])]
    private bool $isMeasurable = true;

    /**
     * @var Collection<int, Muscles>
     */
    #[ORM\OneToMany(targetEntity: Muscles::class, mappedBy: 'bodyPart')]
    private Collection $muscles;

    public function __construct()
    {
        $this->muscles = new ArrayCollection();
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isMeasurable(): bool
    {
        return $this->isMeasurable;
    }

    public function setIsMeasurable(bool $isMeasurable): static
    {
        $this->isMeasurable = $isMeasurable;

        return $this;
    }

    /**
     * @return Collection<int, Muscles>
     */
    public function getMuscles(): Collection
    {
        return $this->muscles;
    }

    public function addMuscle(Muscles $muscle): static
    {
        if (!$this->muscles->contains($muscle)) {
            $this->muscles->add($muscle);
            $muscle->setBodyPart($this);
        }

        return $this;
    }

    public function removeMuscle(Muscles $muscle): static
    {
        if ($this->muscles->removeElement($muscle)) {
            // set the owning side to null (unless already changed)
            if ($muscle->getBodyPart() === $this) {
                $muscle->setBodyPart(null);
            }
        }

        return $this;
    }
}
