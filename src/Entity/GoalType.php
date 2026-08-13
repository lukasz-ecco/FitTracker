<?php

namespace App\Entity;

use App\Repository\GoalTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoalTypeRepository::class)]
#[ORM\Table(name: 'goal_type')]
class GoalType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Klucz maszynowy celu (np. 'weight_loss'). Używany do identyfikacji w logice biznesowej.
     */
    #[ORM\Column(length: 50, unique: true)]
    private ?string $name = null;

    /**
     * Polska nazwa wyświetlana użytkownikowi (np. 'Redukcja wagi').
     */
    #[ORM\Column(length: 100)]
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
