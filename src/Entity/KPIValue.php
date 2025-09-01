<?php

namespace App\Entity;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\Period;
use App\Repository\KPIValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * KPI-Wert Entity für die Speicherung der erfassten Werte.
 */
#[ORM\Entity(repositoryClass: KPIValueRepository::class)]
class KPIValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /**
     * Eindeutige ID des KPI-Werts (Auto-Increment).
     *
     * @var int|null
     */
    private ?int $id = null;

    #[ORM\Embedded(class: DecimalValue::class, columnPrefix: false)]
    #[Assert\NotNull(message: 'Der Wert ist erforderlich.')]
    #[Assert\Valid]
    /**
     * Erfasster Wert.
     *
     * @var DecimalValue|null
     */
    private ?DecimalValue $value = null;

    /**
     * Zeitraumbezug (z.B. "2024-01", "2024-W05", "2024-Q1").
     */
    #[ORM\Embedded(class: Period::class, columnPrefix: false)]
    #[Assert\NotNull(message: 'Der Zeitraum ist erforderlich.')]
    #[Assert\Valid]
    /**
     * Zeitraumbezug (z.B. "2024-01", "2024-W05", "2024-Q1").
     *
     * @var Period|null
     */
    private ?Period $period = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    /**
     * Optionaler Kommentar zum Wert.
     *
     * @var string|null
     */
    private ?string $comment = null;

    #[ORM\Column]
    /**
     * Zeitpunkt der Erstellung des Werts.
     * Wird automatisch beim Anlegen gesetzt.
     *
     * @var \DateTimeImmutable|null
     */
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    /**
     * Zeitpunkt der letzten Aktualisierung (optional).
     *
     * @var \DateTimeImmutable|null
     */
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * KPI zu der dieser Wert gehört.
     */
    #[ORM\ManyToOne(inversedBy: 'values')]
    #[ORM\JoinColumn(nullable: false)]
    /**
     * KPI zu der dieser Wert gehört.
     *
     * @var KPI|null
     */
    private ?KPI $kpi = null;

    /**
     * Dateien die zu diesem Wert gehören.
     */
    #[ORM\OneToMany(mappedBy: 'kpiValue', targetEntity: KPIFile::class, orphanRemoval: true)]
    /**
     * Dateien die zu diesem Wert gehören.
     *
     * @var Collection<int, KPIFile>
     */
    private Collection $files;

    /**
     * Konstruktor initialisiert die Files-Collection und setzt createdAt.
     */
    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Gibt die eindeutige ID des Werts zurück.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den erfassten Wert zurück.
     *
     * @return DecimalValue|null
     */
    public function getValue(): ?DecimalValue
    {
        return $this->value;
    }

    /**
     * Setzt den erfassten Wert.
     *
     * @param DecimalValue $value
     *
     * @return static
     */
    public function setValue(DecimalValue $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Gibt den Wert als Float zurück für Berechnungen.
     */
    public function getValueAsFloat(): float
    {
        return $this->value->toFloat();
    }

    /**
     * Gibt den Zeitraumbezug zurück.
     *
     * @return Period|null
     */
    public function getPeriod(): ?Period
    {
        return $this->period;
    }

    /**
     * Setzt den Zeitraumbezug.
     *
     * @param Period $period
     *
     * @return static
     */
    public function setPeriod(Period $period): static
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Gibt den Kommentar zum Wert zurück.
     *
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Setzt den Kommentar zum Wert.
     *
     * @param string|null $comment
     *
     * @return static
     */
    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gibt das Erstellungsdatum des Werts zurück.
     *
     * @return \DateTimeImmutable|null
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Setzt das Erstellungsdatum des Werts.
     *
     * @param \DateTimeImmutable $createdAt
     *
     * @return static
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gibt das Aktualisierungsdatum des Werts zurück.
     *
     * @return \DateTimeImmutable|null
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Setzt das Aktualisierungsdatum des Werts.
     *
     * @param \DateTimeImmutable|null $updatedAt
     *
     * @return static
     */
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Gibt die zugehörige KPI zurück.
     *
     * @return KPI|null
     */
    public function getKpi(): ?KPI
    {
        return $this->kpi;
    }

    /**
     * Setzt die zugehörige KPI.
     *
     * @param KPI|null $kpi
     *
     * @return static
     */
    public function setKpi(?KPI $kpi): static
    {
        $this->kpi = $kpi;

        return $this;
    }

    /**
     * @return Collection<int, KPIFile>
     */
    /**
     * Gibt alle zugehörigen Dateien als Collection zurück.
     *
     * @return Collection<int, KPIFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    /**
     * Fügt eine Datei zum Wert hinzu (idempotent).
     * Setzt automatisch die Rückreferenz.
     *
     * @param KPIFile $file
     *
     * @return static
     */
    public function addFile(KPIFile $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setKpiValue($this);
        }

        return $this;
    }

    /**
     * Entfernt eine Datei vom Wert.
     * Entfernt auch die Rückreferenz falls gesetzt.
     *
     * @param KPIFile $file
     *
     * @return static
     */
    public function removeFile(KPIFile $file): static
    {
        if ($this->files->removeElement($file)) {
            if ($file->getKpiValue() === $this) {
                $file->setKpiValue(null);
            }
        }

        return $this;
    }

    /**
     * Formatiert den Zeitraum für die Anzeige.
     */
    /**
     * Formatiert den Zeitraum für die Anzeige (z.B. "Januar 2024", "KW 5/2024", "Q1 2024").
     *
     * @return string
     */
    public function getFormattedPeriod(): string
    {
        return $this->period?->format() ?? 'Unbekannter Zeitraum';
    }

    /**
     * Markiert den Eintrag als aktualisiert.
     */
    /**
     * Markiert den Eintrag als aktualisiert (setzt updatedAt).
     *
     * @return static
     */
    public function markAsUpdated(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * String-Repräsentation des Werts (Wert und Zeitraum).
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->value.' ('.$this->getFormattedPeriod().')';
    }
}
