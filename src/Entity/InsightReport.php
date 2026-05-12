<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InsightReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private UploadedFile $uploadedFile;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column]
    private array $kpis = [];

    #[ORM\Column]
    private array $charts = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $project): self { $this->project = $project; return $this; }
    public function getUploadedFile(): UploadedFile { return $this->uploadedFile; }
    public function setUploadedFile(UploadedFile $uploadedFile): self { $this->uploadedFile = $uploadedFile; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $summary): self { $this->summary = $summary; return $this; }
    public function getKpis(): array { return $this->kpis; }
    public function setKpis(array $kpis): self { $this->kpis = $kpis; return $this; }
    public function getCharts(): array { return $this->charts; }
    public function setCharts(array $charts): self { $this->charts = $charts; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

