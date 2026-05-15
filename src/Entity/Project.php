<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, UploadedFile> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: UploadedFile::class, orphanRemoval: true)]
    private Collection $uploadedFiles;

    /** @var Collection<int, ProjectShare> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectShare::class, orphanRemoval: true)]
    private Collection $shares;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->uploadedFiles = new ArrayCollection();
        $this->shares = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getCustomer(): Customer { return $this->customer; }
    public function setCustomer(Customer $customer): self { $this->customer = $customer; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getUploadedFiles(): Collection { return $this->uploadedFiles; }
    public function getShares(): Collection { return $this->shares; }
    public function isSharedWith(User $user): bool
    {
        foreach ($this->shares as $share) {
            if ($share->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
