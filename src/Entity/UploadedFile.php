<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class UploadedFile
{
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PARSED = 'parsed';
    public const STATUS_MAPPED = 'mapped';
    public const STATUS_ANALYZED = 'analyzed';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'uploadedFiles')]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 120)]
    private string $mimeType = '';

    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_UPLOADED;

    #[ORM\Column]
    private int $rowCount = 0;

    #[ORM\Column]
    private int $fileSizeBytes = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /** @var Collection<int, DatasetColumn> */
    #[ORM\OneToMany(mappedBy: 'uploadedFile', targetEntity: DatasetColumn::class, orphanRemoval: true)]
    private Collection $columns;

    /** @var Collection<int, DatasetRow> */
    #[ORM\OneToMany(mappedBy: 'uploadedFile', targetEntity: DatasetRow::class, orphanRemoval: true)]
    private Collection $rows;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->columns = new ArrayCollection();
        $this->rows = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $project): self { $this->project = $project; return $this; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): self { $this->filename = $filename; return $this; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): self { $this->originalName = $originalName; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): self { $this->mimeType = $mimeType; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getRowCount(): int { return $this->rowCount; }
    public function setRowCount(int $rowCount): self { $this->rowCount = $rowCount; return $this; }
    public function getFileSizeBytes(): int { return $this->fileSizeBytes; }
    public function setFileSizeBytes(int $fileSizeBytes): self { $this->fileSizeBytes = $fileSizeBytes; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): self { $this->errorMessage = $errorMessage; return $this; }
    public function getColumns(): Collection { return $this->columns; }
    public function getRows(): Collection { return $this->rows; }
}
