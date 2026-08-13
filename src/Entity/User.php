<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Ignore]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Ignore]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Ignore]
    private ?string $password = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $surrname = null;

    #[ORM\Column(nullable: true)]
    private ?int $age = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?int $gender = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?int $height = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?int $weight = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $profilePicture = null;
    

    #[ORM\Column]
    #[Ignore]
    private bool $isVerified = false;

    /**
     * @var Collection<int, Meseurments>
     */
    #[ORM\OneToMany(targetEntity: Meseurments::class, mappedBy: 'User')]
    private Collection $meseurments;

    /**
     * @var Collection<int, TrainingGoal>
     */
    #[ORM\OneToMany(targetEntity: TrainingGoal::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
    private Collection $trainingGoals;

    public function __construct()
    {
        $this->meseurments = new ArrayCollection();
        $this->trainingGoals = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, TrainingGoal>
     */
    public function getTrainingGoals(): Collection
    {
        return $this->trainingGoals;
    }

    public function addTrainingGoal(TrainingGoal $trainingGoal): static
    {
        if (!$this->trainingGoals->contains($trainingGoal)) {
            $this->trainingGoals->add($trainingGoal);
            $trainingGoal->setUser($this);
        }

        return $this;
    }

    public function removeTrainingGoal(TrainingGoal $trainingGoal): static
    {
        if ($this->trainingGoals->removeElement($trainingGoal)) {
            // set the owning side to null (unless already changed)
            if ($trainingGoal->getUser() === $this) {
                $trainingGoal->setUser(null);
            }
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
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

    public function getSurrname(): ?string
    {
        return $this->surrname;
    }

    public function setSurrname(string $surrname): static
    {
        $this->surrname = $surrname;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getGender(): ?int
    {
        return $this->gender;
    }

    public function setGender(int $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    /**
     * @return Collection<int, Meseurments>
     */
    public function getMeseurments(): Collection
    {
        return $this->meseurments;
    }

    public function addMeseurment(Meseurments $meseurment): static
    {
        if (!$this->meseurments->contains($meseurment)) {
            $this->meseurments->add($meseurment);
            $meseurment->setUser($this);
        }

        return $this;
    }

    public function removeMeseurment(Meseurments $meseurment): static
    {
        if ($this->meseurments->removeElement($meseurment)) {
            // set the owning side to null (unless already changed)
            if ($meseurment->getUser() === $this) {
                $meseurment->setUser(null);
            }
        }

        return $this;
    }
}
