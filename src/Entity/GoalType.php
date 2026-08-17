<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\GoalTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: GoalTypeRepository::class)]
#[ORM\Table(name: 'goal_type')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection()
    ]
)]
class GoalType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goal:read'])]
    private ?int $id = null;

    /**
     * Klucz maszynowy celu (np. 'weight_loss'). Używany do identyfikacji w logice biznesowej.
     */
    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['goal:read'])]
    private ?string $name = null;

    /**
     * Polska nazwa wyświetlana użytkownikowi (np. 'Redukcja wagi').
     */
    #[ORM\Column(length: 100)]
    #[Groups(['goal:read'])]
    private ?string $label = null;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label ?? '';
    }
}
