<?php

namespace App\Domain\Event;

use App\Domain\ValueObject\KPIStatus;
use App\Entity\KPI;
use App\Entity\User;

/**
 * Domain Event: Eine KPI ist überfällig geworden.
 *
 * Wird ausgelöst wenn:
 * - Eine KPI den Status von gelb/grün zu rot wechselt
 * - Triggert automatische Erinnerungen und Eskalationen
 * - Ermöglicht proaktive Benachrichtigungen
 */
readonly class KPIBecameOverdue
{
    public function __construct(
        public KPI $kpi,
        public User $user,
        public KPIStatus $previousStatus,
        public KPIStatus $currentStatus,
        public int $daysOverdue,
        public \DateTimeImmutable $dueDate,
        public \DateTimeImmutable $occurredOn,
        public array $context = [],
    ) {
    }

    /**
     * Factory-Methode für Event-Erstellung.
     */
    public static function create(
        KPI $kpi, 
        KPIStatus $previousStatus, 
        KPIStatus $currentStatus,
        int $daysOverdue,
        \DateTimeImmutable $dueDate,
        array $context = []
    ): self {
        return new self(
            kpi: $kpi,
            user: $kpi->getUser(),
            previousStatus: $previousStatus,
            currentStatus: $currentStatus,
            daysOverdue: $daysOverdue,
            dueDate: $dueDate,
            occurredOn: new \DateTimeImmutable(),
            context: $context,
        );
    }

    /**
     * Gibt eine eindeutige Ereignis-ID zurück.
     */
    public function getEventId(): string
    {
        return sprintf(
            'kpi_became_overdue_%s_%s',
            $this->kpi->getId(),
            $this->occurredOn->format('YmdHis')
        );
    }

    /**
     * Gibt den Event-Type als String zurück.
     */
    public function getEventType(): string
    {
        return 'kpi.became.overdue';
    }

    /**
     * Prüft ob dies das erste Mal ist, dass die KPI überfällig wird.
     */
    public function isFirstTimeOverdue(): bool
    {
        return $this->previousStatus->isYellow() || $this->previousStatus->isGreen();
    }

    /**
     * Berechnet die Eskalationsstufe basierend auf Tagen überfällig.
     */
    public function getEscalationLevel(): int
    {
        return match (true) {
            $this->daysOverdue <= 1 => 1,    // Leicht überfällig
            $this->daysOverdue <= 3 => 2,    // Moderat überfällig
            $this->daysOverdue <= 7 => 3,    // Stark überfällig
            $this->daysOverdue <= 14 => 4,   // Kritisch überfällig
            default => 5,                     // Extrem überfällig
        };
    }

    /**
     * Prüft ob dies eine kritische KPI ist (basierend auf Namen/Tags).
     */
    public function isCriticalKPI(): bool
    {
        $name = strtolower($this->kpi->getName());
        $criticalKeywords = ['umsatz', 'gewinn', 'revenue', 'critical', 'kritisch'];
        
        foreach ($criticalKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }
        
        return $this->context['is_critical'] ?? false;
    }

    /**
     * Gibt die Dringlichkeitsstufe für Benachrichtigungen zurück.
     */
    public function getUrgencyLevel(): string
    {
        if ($this->isCriticalKPI()) {
            return 'critical';
        }

        return match ($this->getEscalationLevel()) {
            1, 2 => 'medium',
            3, 4 => 'high',
            default => 'critical',
        };
    }

    /**
     * Berechnet den nächsten Erinnerungs-Zeitpunkt.
     */
    public function getNextReminderTime(): \DateTimeImmutable
    {
        $escalationLevel = $this->getEscalationLevel();
        
        $hoursUntilNextReminder = match ($escalationLevel) {
            1 => 24,      // 1 Tag später
            2 => 12,      // 12 Stunden später
            3 => 8,       // 8 Stunden später  
            4 => 4,       // 4 Stunden später
            default => 2, // 2 Stunden später
        };

        return $this->occurredOn->modify("+{$hoursUntilNextReminder} hours");
    }

    /**
     * Gibt empfohlene Aktionen für den Benutzer zurück.
     */
    public function getRecommendedActions(): array
    {
        $actions = [
            [
                'type' => 'add_value',
                'label' => 'KPI-Wert erfassen',
                'url' => "/kpi/{$this->kpi->getId()}/add-value",
                'priority' => 'high',
            ]
        ];

        if ($this->getEscalationLevel() >= 3) {
            $actions[] = [
                'type' => 'contact_support',
                'label' => 'Support kontaktieren',
                'priority' => 'medium',
            ];
        }

        if ($this->isCriticalKPI()) {
            array_unshift($actions, [
                'type' => 'escalate_immediately',
                'label' => 'Sofortige Eskalation',
                'priority' => 'critical',
            ]);
        }

        return $actions;
    }

    /**
     * Exportiert Event-Daten als Array.
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->getEventId(),
            'event_type' => $this->getEventType(),
            'occurred_on' => $this->occurredOn->format(\DateTimeInterface::ATOM),
            'kpi_id' => $this->kpi->getId(),
            'kpi_name' => $this->kpi->getName(),
            'kpi_interval' => $this->kpi->getInterval()?->value,
            'user_id' => $this->user->getId(),
            'user_email' => $this->user->getEmail(),
            'previous_status' => $this->previousStatus->toString(),
            'current_status' => $this->currentStatus->toString(),
            'days_overdue' => $this->daysOverdue,
            'due_date' => $this->dueDate->format(\DateTimeInterface::ATOM),
            'escalation_level' => $this->getEscalationLevel(),
            'urgency_level' => $this->getUrgencyLevel(),
            'is_first_time_overdue' => $this->isFirstTimeOverdue(),
            'is_critical_kpi' => $this->isCriticalKPI(),
            'next_reminder_time' => $this->getNextReminderTime()->format(\DateTimeInterface::ATOM),
            'recommended_actions' => $this->getRecommendedActions(),
            'context' => $this->context,
        ];
    }
}