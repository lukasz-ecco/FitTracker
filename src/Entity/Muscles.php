<?php

namespace App\Entity;

use App\Repository\MusclesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MusclesRepository::class)]
class Muscles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'muscles')]
    private ?BodyParts $bodyPart = null;

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
}
