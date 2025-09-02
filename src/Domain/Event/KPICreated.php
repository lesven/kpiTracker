<?php

namespace App\Domain\Event;

use App\Entity\KPI;
use App\Entity\User;

/**
 * Domain Event: Eine neue KPI wurde erstellt.
 *
 * Wird ausgelöst wenn:
 * - Eine neue KPI erfolgreich erstellt und gespeichert wurde
 * - Triggert Willkommens-Workflows und Setup-Assistenten
 * - Ermöglicht Onboarding-Prozesse und Analytics-Tracking
 */
readonly class KPICreated
{
    public function __construct(
        public KPI $kpi,
        public User $user,
        public \DateTimeImmutable $occurredOn,
        public array $context = [],
    ) {
    }

    /**
     * Factory-Methode für Event-Erstellung.
     */
    public static function create(KPI $kpi, array $context = []): self
    {
        return new self(
            kpi: $kpi,
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
            'kpi_created_%s_%s',
            $this->kpi->getId(),
            $this->occurredOn->format('YmdHis')
        );
    }

    /**
     * Gibt den Event-Type als String zurück.
     */
    public function getEventType(): string
    {
        return 'kpi.created';
    }

    /**
     * Prüft ob dies die erste KPI des Benutzers ist.
     */
    public function isFirstKpiForUser(): bool
    {
        return $this->context['is_first_kpi'] ?? false;
    }

    /**
     * Prüft ob die KPI aus einem Template erstellt wurde.
     */
    public function isCreatedFromTemplate(): bool
    {
        return isset($this->context['template_id']);
    }

    /**
     * Gibt die Template-ID zurück (falls verwendet).
     */
    public function getTemplateId(): ?string
    {
        return $this->context['template_id'] ?? null;
    }

    /**
     * Prüft ob dies eine Import-Operation war.
     */
    public function isImportedKpi(): bool
    {
        return $this->context['imported'] ?? false;
    }

    /**
     * Gibt die Quelle der KPI-Erstellung zurück.
     */
    public function getCreationSource(): string
    {
        if ($this->isImportedKpi()) {
            return 'import';
        }

        if ($this->isCreatedFromTemplate()) {
            return 'template';
        }

        return $this->context['source'] ?? 'manual';
    }

    /**
     * Berechnet die Komplexität der KPI (für Onboarding-Hilfen).
     */
    public function getComplexityLevel(): string
    {
        $score = 0;

        // Basis-Score für grundlegende KPI
        $score += 1;

        // Zielwert definiert
        if ($this->kpi->getTarget()) {
            $score += 1;
        }

        // Beschreibung vorhanden
        if ($this->kpi->getDescription()) {
            $score += 1;
        }

        // Einheit spezifiziert
        if ($this->kpi->getUnit()) {
            $score += 1;
        }

        // Quartalsweise KPIs sind komplexer
        if ($this->kpi->getInterval()?->value === 'quarterly') {
            $score += 1;
        }

        return match (true) {
            $score <= 2 => 'simple',
            $score <= 4 => 'moderate',
            default => 'complex',
        };
    }

    /**
     * Gibt empfohlene nächste Schritte für den Benutzer zurück.
     */
    public function getRecommendedNextSteps(): array
    {
        $steps = [];

        // Immer: Ersten Wert hinzufügen
        $steps[] = [
            'type' => 'add_first_value',
            'label' => 'Ersten KPI-Wert erfassen',
            'description' => 'Erfassen Sie den ersten Wert für Ihre neue KPI',
            'url' => "/kpi/{$this->kpi->getId()}/add-value",
            'priority' => 'high',
        ];

        // Falls kein Zielwert gesetzt
        if (!$this->kpi->getTarget()) {
            $steps[] = [
                'type' => 'set_target',
                'label' => 'Zielwert definieren',
                'description' => 'Definieren Sie einen Zielwert für bessere Auswertungen',
                'url' => "/kpi/{$this->kpi->getId()}/edit",
                'priority' => 'medium',
            ];
        }

        // Falls keine Beschreibung
        if (!$this->kpi->getDescription()) {
            $steps[] = [
                'type' => 'add_description',
                'label' => 'Beschreibung hinzufügen',
                'description' => 'Dokumentieren Sie was diese KPI misst',
                'url' => "/kpi/{$this->kpi->getId()}/edit",
                'priority' => 'low',
            ];
        }

        // Erste KPI: Tutorial anbieten
        if ($this->isFirstKpiForUser()) {
            array_unshift($steps, [
                'type' => 'tutorial',
                'label' => 'KPI-Tutorial starten',
                'description' => 'Lernen Sie die Grundlagen der KPI-Verwaltung',
                'url' => '/tutorial/kpi-basics',
                'priority' => 'high',
            ]);
        }

        return $steps;
    }

    /**
     * Generiert automatische Tags basierend auf KPI-Eigenschaften.
     */
    public function generateAutoTags(): array
    {
        $tags = [];
        
        // Intervall-basierte Tags
        $tags[] = $this->kpi->getInterval()?->value ?? 'no-interval';
        
        // Name-basierte Tags
        $name = strtolower($this->kpi->getName());
        
        if (str_contains($name, 'umsatz') || str_contains($name, 'revenue')) {
            $tags[] = 'financial';
        }
        
        if (str_contains($name, 'kunde') || str_contains($name, 'customer')) {
            $tags[] = 'customer';
        }
        
        if (str_contains($name, 'kosten') || str_contains($name, 'cost')) {
            $tags[] = 'cost';
        }

        if (str_contains($name, 'prozent') || str_contains($name, '%')) {
            $tags[] = 'percentage';
        }

        // Komplexitäts-Tag
        $tags[] = 'complexity-' . $this->getComplexityLevel();
        
        // Quelle-Tag
        $tags[] = 'source-' . $this->getCreationSource();

        return array_unique($tags);
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
            'kpi_unit' => $this->kpi->getUnit(),
            'has_target' => $this->kpi->getTarget() !== null,
            'has_description' => !empty($this->kpi->getDescription()),
            'user_id' => $this->user->getId(),
            'user_email' => $this->user->getEmail(),
            'creation_source' => $this->getCreationSource(),
            'complexity_level' => $this->getComplexityLevel(),
            'is_first_kpi' => $this->isFirstKpiForUser(),
            'template_id' => $this->getTemplateId(),
            'auto_tags' => $this->generateAutoTags(),
            'recommended_next_steps' => $this->getRecommendedNextSteps(),
            'context' => $this->context,
        ];
    }
}