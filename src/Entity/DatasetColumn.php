<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DatasetColumn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'columns')]
    #[ORM\JoinColumn(nullable: false)]
    private UploadedFile $uploadedFile;

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $detectedType = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $mappedField = null;

    #[ORM\Column(nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column(nullable: true)]
    private ?array $exampleValues = null;

    #[ORM\Column]
    private bool $ignored = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    public function getId(): ?int { return $this->id; }
    public function getUploadedFile(): UploadedFile { return $this->uploadedFile; }
    public function setUploadedFile(UploadedFile $uploadedFile): self { $this->uploadedFile = $uploadedFile; return $this; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): self { $this->originalName = $originalName; return $this; }
    public function getDetectedType(): ?string { return $this->detectedType; }
    public function setDetectedType(?string $detectedType): self { $this->detectedType = $detectedType; return $this; }
    public function getMappedField(): ?string { return $this->mappedField; }
    public function setMappedField(?string $mappedField): self { $this->mappedField = $mappedField; return $this; }
    public function getConfidence(): ?float { return $this->confidence; }
    public function setConfidence(?float $confidence): self { $this->confidence = $confidence; return $this; }
    public function getExampleValues(): ?array { return $this->exampleValues; }
    public function setExampleValues(?array $exampleValues): self { $this->exampleValues = $exampleValues; return $this; }
    public function isIgnored(): bool { return $this->ignored; }
    public function setIgnored(bool $ignored): self { $this->ignored = $ignored; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): self { $this->reason = $reason; return $this; }
}

