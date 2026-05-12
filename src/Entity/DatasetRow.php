<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DatasetRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rows')]
    #[ORM\JoinColumn(nullable: false)]
    private UploadedFile $uploadedFile;

    #[ORM\Column]
    private int $rowNumber = 0;

    #[ORM\Column]
    private array $rawData = [];

    #[ORM\Column(nullable: true)]
    private ?array $normalizedData = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUploadedFile(): UploadedFile { return $this->uploadedFile; }
    public function setUploadedFile(UploadedFile $uploadedFile): self { $this->uploadedFile = $uploadedFile; return $this; }
    public function getRowNumber(): int { return $this->rowNumber; }
    public function setRowNumber(int $rowNumber): self { $this->rowNumber = $rowNumber; return $this; }
    public function getRawData(): array { return $this->rawData; }
    public function setRawData(array $rawData): self { $this->rawData = $rawData; return $this; }
    public function getNormalizedData(): ?array { return $this->normalizedData; }
    public function setNormalizedData(?array $normalizedData): self { $this->normalizedData = $normalizedData; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

