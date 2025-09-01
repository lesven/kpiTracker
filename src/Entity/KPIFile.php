<?php

namespace App\Entity;

use App\Repository\KPIFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * KPI-Datei Entity für Datei-Uploads zu KPI-Werten.
 */
#[ORM\Entity(repositoryClass: KPIFileRepository::class)]
class KPIFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /**
     * Eindeutige ID der Datei (Auto-Increment).
     *
     * @var int|null
     */
    private ?int $id = null;

    /**
     * Gespeicherter Dateiname auf dem Server.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Dateiname ist erforderlich.')]
    /**
     * Gespeicherter Dateiname auf dem Server.
     *
     * @var string|null
     */
    private ?string $filename = null;

    /**
     * Ursprünglicher Dateiname vom Upload.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Originaldateiname ist erforderlich.')]
    /**
     * Ursprünglicher Dateiname vom Upload.
     *
     * @var string|null
     */
    private ?string $originalName = null;

    /**
     * MIME-Type der Datei.
     */
    #[ORM\Column(length: 100, nullable: true)]
    /**
     * MIME-Type der Datei.
     *
     * @var string|null
     */
    private ?string $mimeType = null;

    /**
     * Dateigröße in Bytes.
     */
    #[ORM\Column(nullable: true)]
    /**
     * Dateigröße in Bytes.
     *
     * @var int|null
     */
    private ?int $fileSize = null;

    #[ORM\Column]
    /**
     * Zeitpunkt der Erstellung der Datei.
     * Wird automatisch beim Anlegen gesetzt.
     *
     * @var \DateTimeImmutable|null
     */
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * KPI-Wert zu dem diese Datei gehört.
     */
    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    /**
     * KPI-Wert zu dem diese Datei gehört.
     *
     * @var KPIValue|null
     */
    private ?KPIValue $kpiValue = null;

    /**
     * Konstruktor setzt das Erstellungsdatum.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Gibt die eindeutige ID der Datei zurück.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den gespeicherten Dateinamen zurück.
     *
     * @return string|null
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Setzt den gespeicherten Dateinamen.
     *
     * @param string $filename
     * @return static
     */
    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Gibt den ursprünglichen Dateinamen zurück.
     *
     * @return string|null
     */
    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    /**
     * Setzt den ursprünglichen Dateinamen.
     *
     * @param string $originalName
     * @return static
     */
    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    /**
     * Gibt den MIME-Type der Datei zurück.
     *
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Setzt den MIME-Type der Datei.
     *
     * @param string|null $mimeType
     * @return static
     */
    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Gibt die Dateigröße in Bytes zurück.
     *
     * @return int|null
     */
    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    /**
     * Setzt die Dateigröße in Bytes.
     *
     * @param int|null $fileSize
     * @return static
     */
    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    /**
     * Gibt das Erstellungsdatum der Datei zurück.
     *
     * @return \DateTimeImmutable|null
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Setzt das Erstellungsdatum der Datei.
     *
     * @param \DateTimeImmutable $createdAt
     * @return static
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gibt den zugehörigen KPI-Wert zurück.
     *
     * @return KPIValue|null
     */
    public function getKpiValue(): ?KPIValue
    {
        return $this->kpiValue;
    }

    /**
     * Setzt den zugehörigen KPI-Wert.
     *
     * @param KPIValue|null $kpiValue
     * @return static
     */
    public function setKpiValue(?KPIValue $kpiValue): static
    {
        $this->kpiValue = $kpiValue;

        return $this;
    }

    /**
     * Formatiert die Dateigröße für die Anzeige.
     */
    /**
     * Formatiert die Dateigröße für die Anzeige (Bytes, KB, MB).
     *
     * @return string
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->fileSize) {
            return 'Unbekannt';
        }

        $bytes = $this->fileSize;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' Bytes';
    }

    /**
     * Prüft ob die Datei ein Bild ist.
     */
    /**
     * Prüft ob die Datei ein Bild ist (MIME-Type beginnt mit image/).
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return $this->mimeType && str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Holt die Dateiendung basierend auf dem MIME-Type.
     */
    /**
     * Holt die Dateiendung basierend auf dem MIME-Type oder Dateinamen.
     *
     * @return string
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

    /**
     * String-Repräsentation der Datei (Originalname).
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->originalName ?? '';
    }
}
