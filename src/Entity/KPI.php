<?php

namespace App\Entity;

use App\Repository\KPIRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * KPI-Entity für die Verwaltung von Key Performance Indicators.
 *
 * Diese Entity repräsentiert einen KPI (Key Performance Indicator) im System.
 * KPIs gehören zu einem Benutzer und haben verschiedene Zeitintervalle für die Erfassung.
 * Jeder KPI kann mehrere Werte (KPIValue) haben, die zu verschiedenen Zeiträumen erfasst wurden.
 *
 * Relationships:
 * - User 1:N KPI (ein Benutzer kann mehrere KPIs haben)
 * - KPI 1:N KPIValue (ein KPI kann mehrere Werte haben)
 */
#[ORM\Entity(repositoryClass: KPIRepository::class)]
class KPI
{
    // Intervall-Konstanten
    public const INTERVAL_WEEKLY = 'weekly';
    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_QUARTERLY = 'quarterly';

    // Status-Konstanten für Dashboard
    public const STATUS_GREEN = 'green';
    public const STATUS_YELLOW = 'yellow';
    public const STATUS_RED = 'red';

    // Konfiguration für Status-Berechnung
    public const DAYS_WARNING_THRESHOLD = 3;

    /**
     * Eindeutige ID des KPIs (Auto-Increment).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Name des KPIs (z.B. "Umsatz", "Kundenanzahl").
     * Darf nicht leer sein und maximal 255 Zeichen lang.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'KPI-Name ist erforderlich.')]
    #[Assert\Length(max: 255, maxMessage: 'Der KPI-Name darf maximal {{ limit }} Zeichen lang sein.')]
    private ?string $name = null;

    /**
     * Intervall für die KPI-Erfassung (weekly, monthly, quarterly).
     * Bestimmt wie oft neue Werte für diesen KPI erfasst werden müssen.
     */
    #[ORM\Column(name: '`interval`', length: 20)]
    #[Assert\Choice(
        choices: [self::INTERVAL_WEEKLY, self::INTERVAL_MONTHLY, self::INTERVAL_QUARTERLY],
        message: 'Bitte wählen Sie ein gültiges Intervall aus.'
    )]
    private ?string $interval = null;

    /**
     * Optionale Beschreibung des KPIs für zusätzliche Informationen.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Maßeinheit des KPIs (z.B. "€", "%", "Stück").
     * Optional für bessere Darstellung der Werte.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    /**
     * Zielwert für den KPI als Decimal-String.
     * Wird als String gespeichert um deutsche Zahlenformate zu unterstützen.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $target = null;

    /**
     * Zeitpunkt der Erstellung des KPIs.
     * Wird automatisch beim Anlegen gesetzt.
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Benutzer dem diese KPI zugeordnet ist.
     * Jeder KPI gehört zu genau einem Benutzer (ManyToOne).
     */
    #[ORM\ManyToOne(inversedBy: 'kpis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * Alle Werte die für diese KPI erfasst wurden.
     * Ein KPI kann mehrere Werte haben (OneToMany), orphanRemoval löscht Werte beim KPI-Löschen.
     */
    #[ORM\OneToMany(mappedBy: 'kpi', targetEntity: KPIValue::class, orphanRemoval: true)]
    private Collection $values;

    /**
     * Konstruktor initialisiert die Values-Collection und setzt createdAt.
     */
    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Gibt die eindeutige ID des KPIs zurück.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den Namen des KPIs zurück.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Setzt den Namen des KPIs.
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gibt das Erfassungsintervall zurück (weekly, monthly, quarterly).
     */
    public function getInterval(): ?string
    {
        return $this->interval;
    }

    /**
     * Setzt das Erfassungsintervall für den KPI.
     */
    public function setInterval(string $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * Gibt die optionale Beschreibung des KPIs zurück.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Setzt die Beschreibung des KPIs.
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gibt das Erstellungsdatum zurück.
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Setzt das Erstellungsdatum (hauptsächlich für Tests).
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gibt den zugehörigen Benutzer zurück.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Setzt den zugehörigen Benutzer für diesen KPI.
     */
    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Gibt alle KPI-Werte als Collection zurück.
     *
     * @return Collection<int, KPIValue>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    /**
     * Fügt einen neuen KPI-Wert hinzu (idempotent).
     * Setzt automatisch die Rückreferenz auf diesen KPI.
     */
    public function addValue(KPIValue $value): static
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
            $value->setKpi($this);
        }

        return $this;
    }

    /**
     * Entfernt einen KPI-Wert (defensive Programmierung).
     * Entfernt auch die Rückreferenz falls gesetzt.
     */
    public function removeValue(KPIValue $value): static
    {
        if ($this->values->removeElement($value) && $value->getKpi() === $this) {
            $value->setKpi(null);
        }

        return $this;
    }

    /**
     * Berechnet das nächste Fälligkeitsdatum basierend auf dem Intervall.
     * Verwendet für Dashboard-Status und Reminder-System.
     */
    public function getNextDueDate(): \DateTimeImmutable
    {
        $now = $this->getCurrentDateTime();

        return match ($this->interval) {
            self::INTERVAL_WEEKLY => $now->modify('next monday'),
            self::INTERVAL_MONTHLY => $now->modify('first day of next month'),
            self::INTERVAL_QUARTERLY => $this->getNextQuarterStart(),
            default => $now->modify('+1 week'),
        };
    }

    /**
     * Gibt das aktuelle DateTime-Objekt zurück.
     * Zentrale Methode für Testbarkeit und konsistente Zeitbehandlung.
     */
    private function getCurrentDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * Ermittelt den Beginn des nächsten Quartals.
     * Hilfsmethode für getNextDueDate() bei quarterly-Intervall.
     */
    private function getNextQuarterStart(): \DateTimeImmutable
    {
        $now = $this->getCurrentDateTime();
        $currentMonth = (int) $now->format('n');

        // Bestimme nächsten Quartalsbeginn
        $nextQuarterMonth = match (true) {
            $currentMonth <= 3 => 4,   // Q2
            $currentMonth <= 6 => 7,   // Q3
            $currentMonth <= 9 => 10,  // Q4
            default => 1,              // Q1 nächstes Jahr
        };

        $year = 1 === $nextQuarterMonth ? $now->format('Y') + 1 : $now->format('Y');

        return new \DateTimeImmutable("{$year}-{$nextQuarterMonth}-01");
    }

    /**
     * Prüft ob ein Wert für einen bestimmten Zeitraum bereits existiert.
     * Verhindert doppelte Einträge für denselben Zeitraum.
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
     * Holt den aktuellen Status für das Dashboard (green, yellow, red).
     * Implementiert die Business Logic für KPI-Status basierend auf User Story 9.
     *
     * - green: Wert für aktuellen Zeitraum existiert
     * - yellow: Fällig innerhalb von 3 Tagen
     * - red: Überfällig
     */
    public function getStatus(): string
    {
        $currentPeriod = $this->getCurrentPeriod();

        if ($this->hasValueForPeriod($currentPeriod)) {
            return self::STATUS_GREEN;
        }

        return $this->isCloseToDeadline() ? self::STATUS_YELLOW : self::STATUS_RED;
    }

    /**
     * Prüft ob das Fälligkeitsdatum nahe ist (innerhalb der Warning-Schwelle).
     */
    private function isCloseToDeadline(): bool
    {
        $now = $this->getCurrentDateTime();
        $dueDate = $this->getNextDueDate();
        $daysDiff = $now->diff($dueDate)->days;

        return $daysDiff <= self::DAYS_WARNING_THRESHOLD;
    }

    /**
     * Ermittelt den aktuellen Zeitraum basierend auf dem Intervall.
     * Formatiert Zeiträume für eindeutige Periode-Identifikation:
     * - weekly: Y-W (z.B. "2024-W23")
     * - monthly: Y-m (z.B. "2024-7")
     * - quarterly: Y-Q* (z.B. "2024-Q2")
     */
    public function getCurrentPeriod(): string
    {
        $now = $this->getCurrentDateTime();

        return match ($this->interval) {
            self::INTERVAL_WEEKLY => $now->format('Y-W'),
            self::INTERVAL_MONTHLY => $now->format('Y-m'),
            self::INTERVAL_QUARTERLY => $now->format('Y') . '-Q' . ceil($now->format('n') / 3),
            default => $now->format('Y-m-d'),
        };
    }

    /**
     * Gibt die Maßeinheit des KPIs zurück.
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * Setzt die Maßeinheit des KPIs.
     */
    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Gibt den Zielwert als String zurück (wie in DB gespeichert).
     */
    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * Setzt den Zielwert des KPIs.
     */
    public function setTarget(?string $target): static
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Gibt den Zielwert als Float zurück für Berechnungen.
     * Unterstützt deutsche Zahlenformate (Komma als Dezimaltrennzeichen).
     * Gibt null zurück bei ungültigen oder leeren Werten.
     */
    public function getTargetAsFloat(): ?float
    {
        if (empty($this->target)) {
            return null;
        }

        // Komma durch Punkt ersetzen für deutsche Zahlenformate
        $cleanTarget = str_replace(',', '.', $this->target);

        return is_numeric($cleanTarget) ? (float) $cleanTarget : null;
    }

    /**
     * String-Repräsentation für Formulare und Debug-Ausgaben.
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
