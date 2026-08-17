<?php

namespace App\Entity;

use App\Repository\MeseurmentsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MeseurmentsRepository::class)]
class Meseurments
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['measurement:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['measurement:read'])]
    private ?float $Size = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'body_part_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['measurement:read'])]
    private ?BodyParts $bodyPart = null;

    #[ORM\ManyToOne(inversedBy: 'meseurments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $User = null;

    #[ORM\Column]
    #[Assert\LessThanOrEqual('today', message: 'Data nie może być w przyszłości.')]
    #[Assert\GreaterThanOrEqual('-7 days', message: 'Data nie może być starsza niż 7 dni.')]
    #[Groups(['measurement:read'])]
    private ?\DateTime $date = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

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

    public function getBodyPart(): ?BodyParts
    {
        return $this->bodyPart;
    }

    public function setBodyPart(?BodyParts $bodyPart): static
    {
        $this->bodyPart = $bodyPart;

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
