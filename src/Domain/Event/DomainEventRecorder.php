<?php

namespace App\Domain\Event;

/**
 * Trait für Domain Event Recording in Entities.
 *
 * Ermöglicht es Entities, Domain Events zu sammeln und später
 * durch Event Dispatcher zu verarbeiten.
 */
trait DomainEventRecorder
{
    /**
     * @var object[] Aufgezeichnete Domain Events
     */
    private array $recordedEvents = [];

    /**
     * Zeichnet ein Domain Event auf.
     */
    protected function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Gibt alle aufgezeichneten Events zurück und löscht sie.
     * 
     * @return object[]
     */
    public function getRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    /**
     * Gibt alle Events zurück ohne sie zu löschen (für Tests).
     * 
     * @return object[]
     */
    public function peekRecordedEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * Prüft ob Events aufgezeichnet wurden.
     */
    public function hasRecordedEvents(): bool
    {
        return !empty($this->recordedEvents);
    }

    /**
     * Löscht alle aufgezeichneten Events ohne sie zurückzugeben.
     */
    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    /**
     * Gibt die Anzahl aufgezeichneter Events zurück.
     */
    public function getRecordedEventCount(): int
    {
        return count($this->recordedEvents);
    }
}