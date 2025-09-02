<?php

namespace App\Domain\ValueObject;

/**
 * KPI-Trend Value Object für Trend-Analyse und -Bewertung.
 *
 * Kapselt Trend-Informationen einer KPI mit zusätzlichen Metadaten
 * wie Konfidenz-Level und prozentuale Veränderung für detaillierte Analysen.
 *
 * Trend-Kategorien:
 * - RISING: Werte steigen signifikant (>5% Verbesserung)
 * - FALLING: Werte fallen signifikant (>5% Verschlechterung) 
 * - STABLE: Werte ändern sich minimal (±5%)
 * - NO_DATA: Nicht genügend Daten für Trend-Berechnung
 * - VOLATILE: Sehr unregelmäßige Schwankungen
 */
readonly class KPITrend
{
    /**
     * Trend-Konstanten.
     */
    public const RISING = 'rising';
    public const FALLING = 'falling';
    public const STABLE = 'stable';
    public const NO_DATA = 'no_data';
    public const VOLATILE = 'volatile';

    /**
     * Gültige Trend-Werte.
     */
    private const VALID_TRENDS = [
        self::RISING,
        self::FALLING,
        self::STABLE,
        self::NO_DATA,
        self::VOLATILE,
    ];

    /**
     * Schwellwerte für Trend-Klassifikation (in Prozent).
     */
    private const RISING_THRESHOLD = 5.0;
    private const FALLING_THRESHOLD = -5.0;
    private const VOLATILITY_THRESHOLD = 30.0;

    /**
     * @param string $direction Trend-Richtung (rising, falling, stable, etc.)
     * @param float|null $percentageChange Prozentuale Veränderung (null wenn nicht berechenbar)
     * @param float $confidence Konfidenz-Level der Trend-Berechnung (0.0-1.0)
     * @param int $dataPoints Anzahl der für Trend verwendeten Datenpunkte
     * @param string|null $timeframe Zeitraum der Analyse (z.B. "last 3 months")
     */
    public function __construct(
        public string $direction,
        public ?float $percentageChange = null,
        public float $confidence = 1.0,
        public int $dataPoints = 0,
        public ?string $timeframe = null,
    ) {
        $this->validate();
    }

    /**
     * Factory für "keine Daten" Trend.
     */
    public static function noData(): self
    {
        return new self(
            direction: self::NO_DATA,
            percentageChange: null,
            confidence: 0.0,
            dataPoints: 0,
        );
    }

    /**
     * Factory für steigenden Trend.
     */
    public static function rising(float $percentageChange, float $confidence = 1.0, int $dataPoints = 0): self
    {
        return new self(
            direction: self::RISING,
            percentageChange: $percentageChange,
            confidence: $confidence,
            dataPoints: $dataPoints,
        );
    }

    /**
     * Factory für fallenden Trend.
     */
    public static function falling(float $percentageChange, float $confidence = 1.0, int $dataPoints = 0): self
    {
        return new self(
            direction: self::FALLING,
            percentageChange: $percentageChange,
            confidence: $confidence,
            dataPoints: $dataPoints,
        );
    }

    /**
     * Factory für stabilen Trend.
     */
    public static function stable(float $percentageChange = 0.0, float $confidence = 1.0, int $dataPoints = 0): self
    {
        return new self(
            direction: self::STABLE,
            percentageChange: $percentageChange,
            confidence: $confidence,
            dataPoints: $dataPoints,
        );
    }

    /**
     * Factory für volatilen Trend.
     */
    public static function volatile(float $confidence = 1.0, int $dataPoints = 0): self
    {
        return new self(
            direction: self::VOLATILE,
            percentageChange: null,
            confidence: $confidence,
            dataPoints: $dataPoints,
        );
    }

    /**
     * Intelligente Factory aus numerischen Daten.
     * 
     * @param float $percentageChange Prozentuale Veränderung
     * @param float $volatility Volatilitäts-Maß (Standard-Abweichung etc.)
     * @param int $dataPoints Anzahl Datenpunkte
     * @param float $confidence Konfidenz-Level
     */
    public static function fromData(
        float $percentageChange,
        float $volatility = 0.0,
        int $dataPoints = 0,
        float $confidence = 1.0
    ): self {
        if ($dataPoints < 2) {
            return self::noData();
        }

        if ($volatility > self::VOLATILITY_THRESHOLD) {
            return self::volatile($confidence, $dataPoints);
        }

        if ($percentageChange > self::RISING_THRESHOLD) {
            return self::rising($percentageChange, $confidence, $dataPoints);
        }

        if ($percentageChange < self::FALLING_THRESHOLD) {
            return self::falling($percentageChange, $confidence, $dataPoints);
        }

        return self::stable($percentageChange, $confidence, $dataPoints);
    }

    /**
     * Prüft ob Trend aufwärts zeigt.
     */
    public function isRising(): bool
    {
        return $this->direction === self::RISING;
    }

    /**
     * Prüft ob Trend abwärts zeigt.
     */
    public function isFalling(): bool
    {
        return $this->direction === self::FALLING;
    }

    /**
     * Prüft ob Trend stabil ist.
     */
    public function isStable(): bool
    {
        return $this->direction === self::STABLE;
    }

    /**
     * Prüft ob keine Daten verfügbar sind.
     */
    public function isNoData(): bool
    {
        return $this->direction === self::NO_DATA;
    }

    /**
     * Prüft ob Trend volatil ist.
     */
    public function isVolatile(): bool
    {
        return $this->direction === self::VOLATILE;
    }

    /**
     * Prüft ob der Trend positiv ist (steigend oder stabil).
     */
    public function isPositive(): bool
    {
        return $this->isRising() || $this->isStable();
    }

    /**
     * Prüft ob der Trend negativ ist (fallend).
     */
    public function isNegative(): bool
    {
        return $this->isFalling();
    }

    /**
     * Prüft ob genügend Daten für zuverlässige Trend-Analyse vorhanden sind.
     */
    public function hasReliableData(): bool
    {
        return $this->dataPoints >= 3 && $this->confidence >= 0.7 && !$this->isNoData();
    }

    /**
     * Gibt die Trend-Stärke als Kategorie zurück.
     */
    public function getStrength(): string
    {
        if ($this->isNoData() || $this->percentageChange === null) {
            return 'unbekannt';
        }

        $absChange = abs($this->percentageChange);

        return match (true) {
            $absChange >= 50 => 'sehr stark',
            $absChange >= 20 => 'stark',
            $absChange >= 10 => 'moderat',
            $absChange >= 5 => 'schwach',
            default => 'minimal',
        };
    }

    /**
     * Gibt eine menschenlesbare Beschreibung des Trends zurück.
     */
    public function getDescription(): string
    {
        return match ($this->direction) {
            self::RISING => 'Steigende Tendenz',
            self::FALLING => 'Fallende Tendenz',
            self::STABLE => 'Stabiler Verlauf',
            self::VOLATILE => 'Unregelmäßige Schwankungen',
            self::NO_DATA => 'Keine ausreichenden Daten',
            default => 'Unbekannter Trend',
        };
    }

    /**
     * Gibt das entsprechende Icon für den Trend zurück.
     */
    public function getIcon(): string
    {
        return match ($this->direction) {
            self::RISING => '📈',
            self::FALLING => '📉',
            self::STABLE => '➡️',
            self::VOLATILE => '📊',
            self::NO_DATA => '❓',
            default => '➖',
        };
    }

    /**
     * Gibt die CSS-Klasse für Frontend-Styling zurück.
     */
    public function getCssClass(): string
    {
        return match ($this->direction) {
            self::RISING => 'trend-positive',
            self::FALLING => 'trend-negative',
            self::STABLE => 'trend-neutral',
            self::VOLATILE => 'trend-volatile',
            self::NO_DATA => 'trend-unknown',
            default => 'trend-default',
        };
    }

    /**
     * Formatiert die prozentuale Veränderung als String.
     */
    public function getFormattedChange(): string
    {
        if ($this->percentageChange === null) {
            return 'N/A';
        }

        $sign = $this->percentageChange >= 0 ? '+' : '';
        return $sign . number_format($this->percentageChange, 1) . '%';
    }

    /**
     * Vergleicht diesen Trend mit einem anderen.
     */
    public function compareWith(self $other): string
    {
        if ($this->direction === $other->direction) {
            return 'identisch';
        }

        if ($this->isPositive() && $other->isNegative()) {
            return 'verbessert';
        }

        if ($this->isNegative() && $other->isPositive()) {
            return 'verschlechtert';
        }

        return 'verändert';
    }

    /**
     * String-Repräsentation des Trends.
     */
    public function toString(): string
    {
        return $this->direction;
    }

    /**
     * String-Casting für einfache Verwendung.
     */
    public function __toString(): string
    {
        if ($this->percentageChange !== null) {
            return "{$this->direction} ({$this->getFormattedChange()})";
        }

        return $this->direction;
    }

    /**
     * Exportiert Trend-Daten als Array.
     */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction,
            'percentage_change' => $this->percentageChange,
            'formatted_change' => $this->getFormattedChange(),
            'confidence' => $this->confidence,
            'data_points' => $this->dataPoints,
            'strength' => $this->getStrength(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'css_class' => $this->getCssClass(),
            'is_reliable' => $this->hasReliableData(),
            'timeframe' => $this->timeframe,
        ];
    }

    /**
     * Validiert die Trend-Daten.
     * 
     * @throws \InvalidArgumentException Bei ungültigen Daten
     */
    private function validate(): void
    {
        if (!in_array($this->direction, self::VALID_TRENDS, true)) {
            $validTrends = implode(', ', self::VALID_TRENDS);
            throw new \InvalidArgumentException(
                "Ungültiger Trend: '{$this->direction}'. Erlaubte Werte: {$validTrends}"
            );
        }

        if ($this->confidence < 0.0 || $this->confidence > 1.0) {
            throw new \InvalidArgumentException(
                "Konfidenz-Level muss zwischen 0.0 und 1.0 liegen, '{$this->confidence}' gegeben."
            );
        }

        if ($this->dataPoints < 0) {
            throw new \InvalidArgumentException('Anzahl Datenpunkte darf nicht negativ sein.');
        }
    }
}