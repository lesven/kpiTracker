<?php

namespace App\Entity;

use App\Repository\KPIRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * KPI-Entity für die Verwaltung von Key Performance Indicators
 */
#[ORM\Entity(repositoryClass: KPIRepository::class)]
class KPI
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'KPI-Name ist erforderlich.')]
    #[Assert\Length(max: 255, maxMessage: 'Der KPI-Name darf maximal {{ limit }} Zeichen lang sein.')]
    private ?string $name = null;

    /**
     * Intervall für die KPI-Erfassung (weekly, monthly, quarterly)
     */
    #[ORM\Column(name: '`interval`', length: 20)]
    #[Assert\Choice(
        choices: ['weekly', 'monthly', 'quarterly'],
        message: 'Bitte wählen Sie ein gültiges Intervall aus.'
    )]
    private ?string $interval = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $target = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Benutzer dem diese KPI zugeordnet ist
     */
    #[ORM\ManyToOne(inversedBy: 'kpis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * Alle Werte die für diese KPI erfasst wurden
     */
    #[ORM\OneToMany(mappedBy: 'kpi', targetEntity: KPIValue::class, orphanRemoval: true)]
    private Collection $values;

    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getInterval(): ?string
    {
        return $this->interval;
    }

    public function setInterval(string $interval): static
    {
        $this->interval = $interval;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, KPIValue>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(KPIValue $value): static
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
            $value->setKpi($this);
        }

        return $this;
    }

    public function removeValue(KPIValue $value): static
    {
        if ($this->values->removeElement($value)) {
            if ($value->getKpi() === $this) {
                $value->setKpi(null);
            }
        }

        return $this;
    }

    /**
     * Berechnet das nächste Fälligkeitsdatum basierend auf dem Intervall
     */
    public function getNextDueDate(): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        
        return match ($this->interval) {
            'weekly' => $now->modify('next monday'),
            'monthly' => $now->modify('first day of next month'),
            'quarterly' => $this->getNextQuarterStart(),
            default => $now->modify('+1 week'),
        };
    }

    /**
     * Ermittelt den Beginn des nächsten Quartals
     */
    private function getNextQuarterStart(): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $currentMonth = (int) $now->format('n');
        
        $nextQuarterMonth = match (true) {
            $currentMonth <= 3 => 4,
            $currentMonth <= 6 => 7,
            $currentMonth <= 9 => 10,
            default => 1,
        };
        
        $year = $nextQuarterMonth === 1 ? $now->format('Y') + 1 : $now->format('Y');
        
        return new \DateTimeImmutable("{$year}-{$nextQuarterMonth}-01");
    }

    /**
     * Prüft ob ein Wert für einen bestimmten Zeitraum bereits existiert
     */
    public function hasValueForPeriod(string $period): bool
    {
        foreach ($this->values as $value) {
            if ($value->getPeriod() === $period) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Holt den aktuellen Status für das Dashboard (green, yellow, red)
     */
    public function getStatus(): string
    {
        $now = new \DateTimeImmutable();
        $dueDate = $this->getNextDueDate();
        
        // Aktueller Zeitraum bestimmen
        $currentPeriod = $this->getCurrentPeriod();
        
        if ($this->hasValueForPeriod($currentPeriod)) {
            return 'green'; // Erledigt
        }
        
        $daysDiff = $now->diff($dueDate)->days;
        
        if ($daysDiff <= 3) {
            return 'yellow'; // Bald fällig
        }
        
        return 'red'; // Überfällig
    }

    /**
     * Ermittelt den aktuellen Zeitraum basierend auf dem Intervall
     */
    public function getCurrentPeriod(): string
    {
        $now = new \DateTimeImmutable();
        
        return match ($this->interval) {
            'weekly' => $now->format('Y-W'),
            'monthly' => $now->format('Y-m'),
            'quarterly' => $now->format('Y') . '-Q' . ceil($now->format('n') / 3),
            default => $now->format('Y-m-d'),
        };
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
