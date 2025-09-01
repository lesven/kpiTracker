<?php

namespace App\Entity;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
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
    /**
     * Status-Konstanten für die Dashboard-Anzeige.
     *
     * @var string
     */
    public const STATUS_GREEN = 'green';
    public const STATUS_YELLOW = 'yellow';
    public const STATUS_RED = 'red';

    /**
     * Schwellenwert in Tagen für gelben Status (Warnung).
     *
     * @var int
     */
    public const DAYS_WARNING_THRESHOLD = 3;

    /**
     * Eindeutige ID des KPIs (Auto-Increment).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /**
     * Eindeutige ID des KPIs (Auto-Increment).
     *
     * @var int|null
     */
    private ?int $id = null;

    /**
     * Name des KPIs (z.B. "Umsatz", "Kundenanzahl").
     * Darf nicht leer sein und maximal 255 Zeichen lang.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'KPI-Name ist erforderlich.')]
    #[Assert\Length(max: 255, maxMessage: 'Der KPI-Name darf maximal {{ limit }} Zeichen lang sein.')]
    /**
     * Name des KPIs (z.B. "Umsatz", "Kundenanzahl").
     *
     * @var string|null
     */
    private ?string $name = null;

    /**
     * Intervall für die KPI-Erfassung (weekly, monthly, quarterly).
     * Bestimmt wie oft neue Werte für diesen KPI erfasst werden müssen.
     */
    #[ORM\Column(name: '`interval`', enumType: KpiInterval::class)]
    #[Assert\NotNull(message: 'Bitte wählen Sie ein gültiges Intervall aus.')]
    /**
     * Intervall für die KPI-Erfassung (weekly, monthly, quarterly).
     *
     * @var KpiInterval|null
     */
    private ?KpiInterval $interval = null;

    /**
     * Optionale Beschreibung des KPIs für zusätzliche Informationen.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    /**
     * Optionale Beschreibung des KPIs für zusätzliche Informationen.
     *
     * @var string|null
     */
    private ?string $description = null;

    /**
     * Maßeinheit des KPIs (z.B. "€", "%", "Stück").
     * Optional für bessere Darstellung der Werte.
     */
    #[ORM\Column(length: 50, nullable: true)]
    /**
     * Maßeinheit des KPIs (z.B. "€", "%", "Stück").
     * Optional für bessere Darstellung der Werte.
     *
     * @var string|null
     */
    private ?string $unit = null;

    /**
     * Zielwert für den KPI.
     */
    #[ORM\Embedded(class: DecimalValue::class, columnPrefix: 'target_')]
    #[Assert\Valid]
    /**
     * Zielwert für den KPI.
     *
     * @var \App\Domain\ValueObject\DecimalValue|null
     */
    private ?DecimalValue $target = null;

    /**
     * Zeitpunkt der Erstellung des KPIs.
     * Wird automatisch beim Anlegen gesetzt.
     */
    #[ORM\Column]
    /**
     * Zeitpunkt der Erstellung des KPIs.
     * Wird automatisch beim Anlegen gesetzt.
     *
     * @var \DateTimeImmutable|null
     */
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Benutzer dem diese KPI zugeordnet ist.
     * Jeder KPI gehört zu genau einem Benutzer (ManyToOne).
     */
    #[ORM\ManyToOne(inversedBy: 'kpis')]
    #[ORM\JoinColumn(nullable: false)]
    /**
     * Benutzer dem diese KPI zugeordnet ist.
     * Jeder KPI gehört zu genau einem Benutzer (ManyToOne).
     *
     * @var User|null
     */
    private ?User $user = null;

    /**
     * Alle Werte die für diese KPI erfasst wurden.
     * Ein KPI kann mehrere Werte haben (OneToMany), orphanRemoval löscht Werte beim KPI-Löschen.
     */
    #[ORM\OneToMany(mappedBy: 'kpi', targetEntity: KPIValue::class, orphanRemoval: true)]
    /**
     * Alle Werte die für diese KPI erfasst wurden.
     * Ein KPI kann mehrere Werte haben (OneToMany), orphanRemoval löscht Werte beim KPI-Löschen.
     *
     * @var Collection<int, KPIValue>
     */
    private Collection $values;

    /**
     * Konstruktor initialisiert die Values-Collection und setzt createdAt.
     */
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
    /**
     * Gibt die eindeutige ID des KPIs zurück.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den Namen des KPIs zurück.
     */
    /**
     * Gibt den Namen des KPIs zurück.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Setzt den Namen des KPIs.
     */
    /**
     * Setzt den Namen des KPIs.
     *
     * @param string $name
     *
     * @return static
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gibt das Erfassungsintervall zurück (weekly, monthly, quarterly).
     */
    /**
     * Gibt das Erfassungsintervall zurück (weekly, monthly, quarterly).
     *
     * @return KpiInterval|null
     */
    public function getInterval(): ?KpiInterval
    {
        return $this->interval;
    }

    /**
     * Setzt das Erfassungsintervall für den KPI.
     */
    public function setInterval(KpiInterval $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * Gibt die optionale Beschreibung des KPIs zurück.
     */
    /**
     * Gibt die optionale Beschreibung des KPIs zurück.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Setzt die Beschreibung des KPIs.
     */
    /**
     * Setzt die Beschreibung des KPIs.
     *
     * @param string|null $description
     *
     * @return static
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gibt das Erstellungsdatum zurück.
     */
    /**
     * Gibt das Erstellungsdatum zurück.
     *
     * @return \DateTimeImmutable|null
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Setzt das Erstellungsdatum (hauptsächlich für Tests).
     */
    /**
     * Setzt das Erstellungsdatum (hauptsächlich für Tests).
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
     * Gibt den zugehörigen Benutzer zurück.
     */
    /**
     * Gibt den zugehörigen Benutzer zurück.
     *
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Setzt den zugehörigen Benutzer für diesen KPI.
     */
    /**
     * Setzt den zugehörigen Benutzer für diesen KPI.
     *
     * @param User|null $user
     *
     * @return static
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
    /**
     * Fügt einen neuen KPI-Wert hinzu (idempotent).
     * Setzt automatisch die Rückreferenz auf diesen KPI.
     *
     * @param KPIValue $value
     *
     * @return static
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
    /**
     * Entfernt einen KPI-Wert (defensive Programmierung).
     * Entfernt auch die Rückreferenz falls gesetzt.
     *
     * @param KPIValue $value
     *
     * @return static
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
    /**
     * Berechnet das nächste Fälligkeitsdatum basierend auf dem Intervall.
     * Verwendet für Dashboard-Status und Reminder-System.
     *
     * @return \DateTimeImmutable
     */
    public function getNextDueDate(): \DateTimeImmutable
    {
        $now = $this->getCurrentDateTime();

        return match ($this->interval) {
            KpiInterval::WEEKLY => $now->modify('next monday'),
            KpiInterval::MONTHLY => $now->modify('first day of next month'),
            KpiInterval::QUARTERLY => $this->getNextQuarterStart(),
            default => $now->modify('+1 week'),
        };
    }

    /**
     * Gibt das aktuelle DateTime-Objekt zurück.
     * Zentrale Methode für Testbarkeit und konsistente Zeitbehandlung.
     */
    /**
     * Gibt das aktuelle DateTime-Objekt zurück.
     * Zentrale Methode für Testbarkeit und konsistente Zeitbehandlung.
     *
     * @return \DateTimeImmutable
     */
    private function getCurrentDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * Ermittelt den Beginn des nächsten Quartals.
     * Hilfsmethode für getNextDueDate() bei quarterly-Intervall.
     */
    /**
     * Ermittelt den Beginn des nächsten Quartals.
     * Hilfsmethode für getNextDueDate() bei quarterly-Intervall.
     *
     * @return \DateTimeImmutable
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
    /**
     * Prüft ob ein Wert für einen bestimmten Zeitraum bereits existiert.
     * Verhindert doppelte Einträge für denselben Zeitraum.
     *
     * @param Period $period
     *
     * @return bool
     */
    public function hasValueForPeriod(Period $period): bool
    {
        foreach ($this->values as $value) {
            if ($value->getPeriod() && $value->getPeriod()->value() === $period->value()) {
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
    /**
     * Holt den aktuellen Status für das Dashboard (green, yellow, red).
     * Implementiert die Business Logic für KPI-Status basierend auf User Story 9.
     *
     * - green: Wert für aktuellen Zeitraum existiert
     * - yellow: Fällig innerhalb von 3 Tagen
     * - red: Überfällig
     *
     * @return string
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
    /**
     * Prüft ob das Fälligkeitsdatum nahe ist (innerhalb der Warning-Schwelle).
     *
     * @return bool
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
     * - quarterly: Y-Q* (z.B. "2024-Q2").
     */
    /**
     * Ermittelt den aktuellen Zeitraum basierend auf dem Intervall.
     * Formatiert Zeiträume für eindeutige Periode-Identifikation:
     * - weekly: Y-W (z.B. "2024-W23")
     * - monthly: Y-m (z.B. "2024-7")
     * - quarterly: Y-Q* (z.B. "2024-Q2").
     *
     * @return Period
     */
    public function getCurrentPeriod(): Period
    {
        $now = $this->getCurrentDateTime();

        $periodString = match ($this->interval) {
            KpiInterval::WEEKLY => $now->format('Y-\WW'),
            KpiInterval::MONTHLY => $now->format('Y-m'),
            KpiInterval::QUARTERLY => $now->format('Y').'-Q'.ceil($now->format('n') / 3),
            default => $now->format('Y-m-d'),
        };

        return Period::fromString($periodString);
    }

    /**
     * Gibt die Maßeinheit des KPIs zurück.
     */
    /**
     * Gibt die Maßeinheit des KPIs zurück.
     *
     * @return string|null
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * Setzt die Maßeinheit des KPIs.
     */
    /**
     * Setzt die Maßeinheit des KPIs.
     *
     * @param string|null $unit
     *
     * @return static
     */
    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Gibt den Zielwert als String zurück (wie in DB gespeichert).
     */
    /**
     * Gibt den Zielwert zurück.
     *
     * @return DecimalValue|null
     */
    public function getTarget(): ?DecimalValue
    {
        return $this->target;
    }

    /**
     * Setzt den Zielwert des KPIs.
     *
     * @param DecimalValue|null $target
     *
     * @return static
     */
    public function setTarget(?DecimalValue $target): static
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Gibt den Zielwert als Float zurück für Berechnungen.
     */
    public function getTargetAsFloat(): ?float
    {
        return $this->target?->toFloat();
    }

    /**
     * String-Repräsentation für Formulare und Debug-Ausgaben.
     */
    /**
     * String-Repräsentation für Formulare und Debug-Ausgaben.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
