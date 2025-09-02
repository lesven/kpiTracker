<?php

namespace App\Domain\ValueObject;

use App\Entity\KPIValue;

/**
 * KPI-Statistiken Value Object für mathematische Auswertungen.
 *
 * Kapselt alle statistischen Kennzahlen einer KPI in einem unveränderlichen Objekt.
 * Verhindert inkonsistente Zustände und bietet typsichere Zugriffe auf alle Metriken.
 *
 * Statistiken umfassen:
 * - Grundkennzahlen (Anzahl, Durchschnitt, Min/Max)
 * - Zeitbasierte Daten (neuester/ältester Wert)
 * - Trend-Analyse Integration
 */
readonly class KPIStatistics
{
    /**
     * Erstellt Statistiken aus den berechneten Daten.
     *
     * @param int $totalEntries Gesamtanzahl der erfassten Werte
     * @param float|null $averageValue Durchschnittswert (null wenn keine Daten)
     * @param float|null $minValue Minimum aller Werte
     * @param float|null $maxValue Maximum aller Werte
     * @param KPIValue|null $latestValue Chronologisch neuester Wert
     * @param KPIValue|null $oldestValue Chronologisch ältester Wert
     * @param KPITrend $trend Trend-Analyse der letzten Werte
     * @param float|null $variance Varianz der Werte (für Streuungs-Analyse)
     * @param float|null $standardDeviation Standardabweichung
     */
    public function __construct(
        public int $totalEntries,
        public ?float $averageValue,
        public ?float $minValue,
        public ?float $maxValue,
        public ?KPIValue $latestValue,
        public ?KPIValue $oldestValue,
        public KPITrend $trend,
        public ?float $variance = null,
        public ?float $standardDeviation = null,
    ) {
        $this->validate();
    }

    /**
     * Factory für leere Statistiken (keine Daten vorhanden).
     */
    public static function empty(): self
    {
        return new self(
            totalEntries: 0,
            averageValue: null,
            minValue: null,
            maxValue: null,
            latestValue: null,
            oldestValue: null,
            trend: KPITrend::noData(),
            variance: null,
            standardDeviation: null,
        );
    }

    /**
     * Factory für Basis-Statistiken (ohne erweiterte Metriken).
     */
    public static function fromBasicData(
        int $totalEntries,
        ?float $averageValue,
        ?float $minValue,
        ?float $maxValue,
        ?KPIValue $latestValue,
        KPITrend $trend
    ): self {
        return new self(
            totalEntries: $totalEntries,
            averageValue: $averageValue,
            minValue: $minValue,
            maxValue: $maxValue,
            latestValue: $latestValue,
            oldestValue: null, // wird später gesetzt wenn verfügbar
            trend: $trend,
        );
    }

    /**
     * Prüft ob überhaupt Daten vorhanden sind.
     */
    public function hasData(): bool
    {
        return $this->totalEntries > 0;
    }

    /**
     * Prüft ob genügend Daten für Trend-Analyse vorhanden sind.
     */
    public function hasSufficientDataForTrend(): bool
    {
        return $this->totalEntries >= 2;
    }

    /**
     * Prüft ob erweiterte Statistiken verfügbar sind.
     */
    public function hasAdvancedMetrics(): bool
    {
        return $this->variance !== null && $this->standardDeviation !== null;
    }

    /**
     * Gibt die Spannweite (Range) der Werte zurück.
     */
    public function getRange(): ?float
    {
        if ($this->minValue === null || $this->maxValue === null) {
            return null;
        }

        return $this->maxValue - $this->minValue;
    }

    /**
     * Berechnet den Variationskoeffizienten (relative Streuung).
     * 
     * @return float|null Wert zwischen 0 und 1 (null wenn nicht berechenbar)
     */
    public function getCoefficientOfVariation(): ?float
    {
        if ($this->standardDeviation === null || $this->averageValue === null || $this->averageValue == 0) {
            return null;
        }

        return abs($this->standardDeviation / $this->averageValue);
    }

    /**
     * Prüft ob die Datenqualität als "gut" eingestuft werden kann.
     * Basiert auf Anzahl der Datenpunkte und Trend-Verfügbarkeit.
     */
    public function hasGoodDataQuality(): bool
    {
        return $this->totalEntries >= 5 && !$this->trend->isNoData();
    }

    /**
     * Gibt eine Bewertung der Datenstabilität zurück.
     */
    public function getStabilityRating(): string
    {
        if (!$this->hasAdvancedMetrics()) {
            return 'unbekannt';
        }

        $cv = $this->getCoefficientOfVariation();
        
        if ($cv === null) {
            return 'unbekannt';
        }

        return match (true) {
            $cv <= 0.1 => 'sehr stabil',
            $cv <= 0.2 => 'stabil', 
            $cv <= 0.5 => 'moderat',
            default => 'volatil',
        };
    }

    /**
     * Vergleicht diese Statistiken mit anderen (für A/B Tests etc.).
     */
    public function compareWith(self $other): array
    {
        return [
            'entries_diff' => $this->totalEntries - $other->totalEntries,
            'average_diff' => $this->calculateDifference($this->averageValue, $other->averageValue),
            'min_diff' => $this->calculateDifference($this->minValue, $other->minValue),
            'max_diff' => $this->calculateDifference($this->maxValue, $other->maxValue),
            'trend_comparison' => $this->trend->compareWith($other->trend),
        ];
    }

    /**
     * Exportiert die Statistiken als Array (für JSON/API).
     */
    public function toArray(): array
    {
        return [
            'total_entries' => $this->totalEntries,
            'average_value' => $this->averageValue,
            'min_value' => $this->minValue,
            'max_value' => $this->maxValue,
            'latest_value' => $this->latestValue?->getValueAsFloat(),
            'oldest_value' => $this->oldestValue?->getValueAsFloat(),
            'trend' => $this->trend->toString(),
            'range' => $this->getRange(),
            'variance' => $this->variance,
            'standard_deviation' => $this->standardDeviation,
            'coefficient_of_variation' => $this->getCoefficientOfVariation(),
            'stability_rating' => $this->getStabilityRating(),
            'data_quality_good' => $this->hasGoodDataQuality(),
        ];
    }

    /**
     * String-Repräsentation für Debug und Logging.
     */
    public function __toString(): string
    {
        if (!$this->hasData()) {
            return 'Keine Daten verfügbar';
        }

        $avg = $this->averageValue !== null ? number_format($this->averageValue, 2) : 'N/A';
        return "KPI-Statistiken: {$this->totalEntries} Einträge, Ø {$avg}, Trend: {$this->trend}";
    }

    /**
     * Berechnet die Differenz zwischen zwei nullable Float-Werten.
     */
    private function calculateDifference(?float $value1, ?float $value2): ?float
    {
        if ($value1 === null || $value2 === null) {
            return null;
        }

        return $value1 - $value2;
    }

    /**
     * Validiert die Konsistenz der Statistik-Daten.
     * 
     * @throws \InvalidArgumentException Bei inkonsistenten Daten
     */
    private function validate(): void
    {
        if ($this->totalEntries < 0) {
            throw new \InvalidArgumentException('Anzahl der Einträge darf nicht negativ sein.');
        }

        if ($this->totalEntries === 0) {
            // Bei null Einträgen sollten alle Werte null sein
            if ($this->averageValue !== null || $this->minValue !== null || 
                $this->maxValue !== null || $this->latestValue !== null) {
                throw new \InvalidArgumentException('Bei null Einträgen dürfen keine Werte gesetzt sein.');
            }
        }

        if ($this->minValue !== null && $this->maxValue !== null && $this->minValue > $this->maxValue) {
            throw new \InvalidArgumentException('Minimum-Wert darf nicht größer als Maximum-Wert sein.');
        }

        if ($this->variance !== null && $this->variance < 0) {
            throw new \InvalidArgumentException('Varianz darf nicht negativ sein.');
        }

        if ($this->standardDeviation !== null && $this->standardDeviation < 0) {
            throw new \InvalidArgumentException('Standardabweichung darf nicht negativ sein.');
        }
    }
}