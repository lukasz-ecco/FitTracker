<?php

namespace App\Entity;

use App\Repository\MeseurmentsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MeseurmentsRepository::class)]
class Meseurments
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $Size = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Muscles $Muscle = null;

    #[ORM\ManyToOne(inversedBy: 'meseurments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $User = null;

    #[ORM\Column]
    private ?\DateTime $date = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSize(): ?float
    {
        return $this->Size;
    }

    public function setSize(float $Size): static
    {
        $this->Size = $Size;

        return $this;
    }

    public function getMuscle(): ?Muscles
    {
        return $this->Muscle;
    }

    public function setMuscle(?Muscles $Muscle): static
    {
        $this->Muscle = $Muscle;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User ): static
    {
        $this->User = $User;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

}
