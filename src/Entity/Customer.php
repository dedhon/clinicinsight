<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 80, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 40)]
    private string $plan = 'starter';

    #[ORM\Column(length: 40)]
    private string $subscriptionStatus = 'trial';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $billingEmail = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: User::class)]
    private Collection $users;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Project::class)]
    private Collection $projects;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->users = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getPlan(): string { return $this->plan; }
    public function setPlan(string $plan): self { $this->plan = $plan; return $this; }
    public function getSubscriptionStatus(): string { return $this->subscriptionStatus; }
    public function setSubscriptionStatus(string $subscriptionStatus): self { $this->subscriptionStatus = $subscriptionStatus; return $this; }
    public function getBillingEmail(): ?string { return $this->billingEmail; }
    public function setBillingEmail(?string $billingEmail): self { $this->billingEmail = $billingEmail; return $this; }
    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function setStripeCustomerId(?string $stripeCustomerId): self { $this->stripeCustomerId = $stripeCustomerId; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUsers(): Collection { return $this->users; }
    public function getProjects(): Collection { return $this->projects; }
}
