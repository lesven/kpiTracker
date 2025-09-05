<?php

namespace App\Domain\Event;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;

/**
 * Domain Event: Ein KPI-Wert wurde hinzugefügt.
 *
 * Wird ausgelöst wenn:
 * - Ein neuer KPI-Wert erfolgreich gespeichert wurde
 * - Kann verschiedene Seiteneffekte auslösen (Notifications, Statistik-Updates, etc.)
 * - Ermöglicht lose Kopplung zwischen Domänen-Services
 */
readonly class KPIValueAdded
{
    public function __construct(
        public KPI $kpi,
        public KPIValue $kpiValue,
        public User $user,
        public \DateTimeImmutable $occurredOn,
        public array $context = [],
    ) {
    }

    /**
     * Factory-Methode für einfache Erstellung.
     */
    public static function create(KPI $kpi, KPIValue $kpiValue, array $context = []): self
    {
        return new self(
            kpi: $kpi,
            kpiValue: $kpiValue,
            user: $kpi->getUser(),
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
            'kpi_value_added_%s_%s_%s',
            $this->kpi->getId(),
            $this->kpiValue->getId(),
            $this->occurredOn->format('YmdHis')
        );
    }

    /**
     * Gibt den Event-Type als String zurück.
     */
    public function getEventType(): string
    {
        return 'kpi.value.added';
    }

    /**
     * Prüft ob dies der erste Wert für diese KPI ist.
     */
    public function isFirstValue(): bool
    {
        return $this->context['is_first_value'] ?? false;
    }

    /**
     * Prüft ob der Wert überschrieben wurde.
     */
    public function wasOverridden(): bool
    {
        return $this->context['was_override'] ?? false;
    }

    /**
     * Gibt den überschriebenen Wert zurück (falls vorhanden).
     */
    public function getOverriddenValue(): ?KPIValue
    {
        return $this->context['overridden_value'] ?? null;
    }

    /**
     * Prüft ob Dateien mit dem Wert hochgeladen wurden.
     */
    public function hasFileUploads(): bool
    {
        return ($this->context['uploaded_files_count'] ?? 0) > 0;
    }

    /**
     * Exportiert Event-Daten als Array (für Logging/Serialization).
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
            'value_id' => $this->kpiValue->getId(),
            'value_amount' => $this->kpiValue->getValueAsFloat(),
            'value_period' => $this->kpiValue->getPeriod()?->value(),
            'context' => $this->context,
            'metadata' => [
                'is_first_value' => $this->isFirstValue(),
                'was_overridden' => $this->wasOverridden(),
                'has_files' => $this->hasFileUploads(),
            ],
        ];
    }
}