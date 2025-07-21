<?php

namespace App\Entity;

use App\Repository\KPIValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * KPI-Wert Entity für die Speicherung der erfassten Werte
 */
#[ORM\Entity(repositoryClass: KPIValueRepository::class)]
class KPIValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Der Wert ist erforderlich.')]
    #[Assert\Type(type: 'numeric', message: 'Der Wert muss eine Zahl sein.')]
    private ?string $value = null;

    /**
     * Zeitraumbezug (z.B. "2024-01", "2024-W05", "2024-Q1")
     */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Der Zeitraum ist erforderlich.')]
    #[Assert\Regex(
        pattern: '/^(\d{4})-(\d{2}|\w\d{2}|Q\d)$/',
        message: 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'
    )]
    private ?string $period = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * KPI zu der dieser Wert gehört
     */
    #[ORM\ManyToOne(inversedBy: 'values')]
    #[ORM\JoinColumn(nullable: false)]
    private ?KPI $kpi = null;

    /**
     * Dateien die zu diesem Wert gehören
     */
    #[ORM\OneToMany(mappedBy: 'kpiValue', targetEntity: KPIFile::class, orphanRemoval: true)]
    private Collection $files;

    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Gibt den Wert als Float zurück für Berechnungen
     */
    public function getValueAsFloat(): float
    {
        return (float) $this->value;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getKpi(): ?KPI
    {
        return $this->kpi;
    }

    public function setKpi(?KPI $kpi): static
    {
        $this->kpi = $kpi;
        return $this;
    }

    /**
     * @return Collection<int, KPIFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(KPIFile $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setKpiValue($this);
        }

        return $this;
    }

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
     * Formatiert den Zeitraum für die Anzeige
     */
    public function getFormattedPeriod(): string
    {
        // Sicherheitscheck für leeren/null Zeitraum
        if (empty($this->period)) {
            return 'Unbekannter Zeitraum';
        }

        // Beispiele: "2024-01" -> "Januar 2024", "2024-W05" -> "KW 5/2024"
        if (preg_match('/^(\d{4})-(\d{2})$/', $this->period, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $monthNames = [
                '01' => 'Januar', '02' => 'Februar', '03' => 'März',
                '04' => 'April', '05' => 'Mai', '06' => 'Juni',
                '07' => 'Juli', '08' => 'August', '09' => 'September',
                '10' => 'Oktober', '11' => 'November', '12' => 'Dezember'
            ];
            
            // Prüfen ob der Monat im Array existiert
            if (isset($monthNames[$month])) {
                return $monthNames[$month] . ' ' . $year;
            } else {
                return 'Monat ' . $month . ' ' . $year;
            }
        }
        
        if (preg_match('/^(\d{4})-W(\d{2})$/', $this->period, $matches)) {
            return 'KW ' . ltrim($matches[2], '0') . '/' . $matches[1];
        }
        
        if (preg_match('/^(\d{4})-Q(\d)$/', $this->period, $matches)) {
            return 'Q' . $matches[2] . ' ' . $matches[1];
        }
        
        return $this->period;
    }

    /**
     * Markiert den Eintrag als aktualisiert
     */
    public function markAsUpdated(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function __toString(): string
    {
        return $this->value . ' (' . $this->getFormattedPeriod() . ')';
    }
}
