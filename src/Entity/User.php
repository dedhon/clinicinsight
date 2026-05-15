<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 5)]
    private string $locale = 'es';

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Project::class, orphanRemoval: true)]
    private Collection $projects;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function setRoles(array $roles): self { $this->roles = array_values(array_unique($roles)); return $this; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): self { $this->locale = $locale; return $this; }
    public function eraseCredentials(): void {}
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getCustomer(): Customer { return $this->customer; }
    public function setCustomer(Customer $customer): self { $this->customer = $customer; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProjects(): Collection { return $this->projects; }
}
