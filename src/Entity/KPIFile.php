<?php

namespace App\Entity;

use App\Repository\KPIFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * KPI-Datei Entity für Datei-Uploads zu KPI-Werten
 */
#[ORM\Entity(repositoryClass: KPIFileRepository::class)]
class KPIFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Gespeicherter Dateiname auf dem Server
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Dateiname ist erforderlich.')]
    private ?string $filename = null;

    /**
     * Ursprünglicher Dateiname vom Upload
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Originaldateiname ist erforderlich.')]
    private ?string $originalName = null;

    /**
     * MIME-Type der Datei
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    /**
     * Dateigröße in Bytes
     */
    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * KPI-Wert zu dem diese Datei gehört
     */
    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    private ?KPIValue $kpiValue = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;
        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getKpiValue(): ?KPIValue
    {
        return $this->kpiValue;
    }

    public function setKpiValue(?KPIValue $kpiValue): static
    {
        $this->kpiValue = $kpiValue;
        return $this;
    }

    /**
     * Formatiert die Dateigröße für die Anzeige
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->fileSize) {
            return 'Unbekannt';
        }

        $bytes = $this->fileSize;
        
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' Bytes';
    }

    /**
     * Prüft ob die Datei ein Bild ist
     */
    public function isImage(): bool
    {
        return $this->mimeType && str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Holt die Dateiendung basierend auf dem MIME-Type
     */
    public function getFileExtension(): string
    {
        return match ($this->mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            default => pathinfo($this->originalName, PATHINFO_EXTENSION) ?: 'unknown',
        };
    }

    public function __toString(): string
    {
        return $this->originalName ?? '';
    }
}
