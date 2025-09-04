<?php

namespace App\Domain\ValueObject;

use JsonSerializable;

/**
 * KPI-Status Value Object für typsichere Status-Repräsentation.
 *
 * Ersetzt primitive String-Konstanten durch eine robuste, typsichere Implementierung
 * mit expliziten Business-Rules für Status-Übergänge und Validierungen.
 *
 * Status-Semantik:
 * - GREEN: KPI-Wert für aktuellen Zeitraum erfasst, alles in Ordnung
 * - YELLOW: KPI wird bald fällig (Warnung innerhalb der nächsten 3 Tage)
 * - RED: KPI ist überfällig, Handlungsbedarf
 */
readonly class KPIStatus implements JsonSerializable
{
    /**
     * Status-Konstanten als Enum-ähnliche Implementierung.
     */
    public const GREEN = 'green';
    public const YELLOW = 'yellow'; 
    public const RED = 'red';

    /**
     * Alle gültigen Status-Werte für Validierung.
     */
    private const VALID_STATUSES = [
        self::GREEN,
        self::YELLOW,
        self::RED,
    ];

    /**
     * Prioritäts-Gewichtung für Status-Vergleiche (höher = kritischer).
     */
    private const STATUS_PRIORITIES = [
        self::GREEN => 1,
        self::YELLOW => 2,
        self::RED => 3,
    ];

    /**
     * @param string $value Der Status-Wert
     * @throws \InvalidArgumentException Bei ungültigem Status-Wert
     */
    public function __construct(
        private string $value
    ) {
        $this->validate($value);
    }

    /**
     * Factory-Methode für GREEN Status.
     */
    public static function green(): self
    {
        return new self(self::GREEN);
    }

    /**
     * Factory-Methode für YELLOW Status.
     */
    public static function yellow(): self
    {
        return new self(self::YELLOW);
    }

    /**
     * Factory-Methode für RED Status.
     */
    public static function red(): self
    {
        return new self(self::RED);
    }

    /**
     * Factory-Methode aus String-Wert.
     * 
     * @param string $status Der Status als String
     * @return self
     * @throws \InvalidArgumentException Bei ungültigem Status
     */
    public static function fromString(string $status): self
    {
        return new self($status);
    }

    /**
     * Gibt den Status-Wert als String zurück.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * String-Casting für einfache Verwendung.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Prüft ob dieser Status grün ist.
     */
    public function isGreen(): bool
    {
        return $this->value === self::GREEN;
    }

    /**
     * Prüft ob dieser Status gelb ist (Warnung).
     */
    public function isYellow(): bool
    {
        return $this->value === self::YELLOW;
    }

    /**
     * Prüft ob dieser Status rot ist (kritisch).
     */
    public function isRed(): bool
    {
        return $this->value === self::RED;
    }

    /**
     * Prüft ob dieser Status kritisch ist (gelb oder rot).
     */
    public function isCritical(): bool
    {
        return $this->isYellow() || $this->isRed();
    }

    /**
     * Prüft ob dieser Status "OK" ist (nur grün).
     */
    public function isOk(): bool
    {
        return $this->isGreen();
    }

    /**
     * Prüft ob dieser Status schlechter (kritischer) als ein anderer ist.
     */
    public function isWorseThan(self $other): bool
    {
        return $this->getPriority() > $other->getPriority();
    }

    /**
     * Prüft ob dieser Status besser als ein anderer ist.
     */
    public function isBetterThan(self $other): bool
    {
        return $this->getPriority() < $other->getPriority();
    }

    /**
     * Equality-Check für Status-Vergleiche.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Gibt die CSS-Klasse für Frontend-Styling zurück.
     */
    public function getCssClass(): string
    {
        return match ($this->value) {
            self::GREEN => 'status-green',
            self::YELLOW => 'status-yellow',
            self::RED => 'status-red',
        };
    }

    /**
     * Gibt eine menschenlesbare Beschreibung des Status zurück.
     */
    public function getDescription(): string
    {
        return match ($this->value) {
            self::GREEN => 'Alle Werte erfasst',
            self::YELLOW => 'Bald fällig',
            self::RED => 'Überfällig',
        };
    }

    /**
     * Gibt das entsprechende Icon für den Status zurück.
     */
    public function getIcon(): string
    {
        return match ($this->value) {
            self::GREEN => '✅',
            self::YELLOW => '⚠️',
            self::RED => '❌',
        };
    }

    /**
     * Gibt alle verfügbaren Status-Werte zurück.
     * 
     * @return array<string>
     */
    public static function getAllStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Ermittelt die Priorität des aktuellen Status (für Sortierung).
     */
    private function getPriority(): int
    {
        return self::STATUS_PRIORITIES[$this->value];
    }

    /**
     * Gibt die Hierarchie-Wert für Vergleiche zurück.
     */
    public function getHierarchyValue(): int
    {
        return self::STATUS_PRIORITIES[$this->value];
    }

    /**
     * Berechnet aggregierten Status aus mehreren Status-Objekten.
     */
    public static function getAggregatedStatus(array $statuses): self
    {
        if (empty($statuses)) {
            return self::green();
        }

        $hasRed = false;
        $hasYellow = false;

        foreach ($statuses as $status) {
            if ($status->isRed()) {
                $hasRed = true;
                break;
            }
            if ($status->isYellow()) {
                $hasYellow = true;
            }
        }

        if ($hasRed) {
            return self::red();
        }
        
        if ($hasYellow) {
            return self::yellow();
        }

        return self::green();
    }

    /**
     * Gibt Emoji für Status zurück.
     */
    public function getEmoji(): string
    {
        return $this->getIcon();
    }

    /**
     * Gibt benutzerfreundliche Nachricht zurück.
     */
    public function getUserFriendlyMessage(): string
    {
        return match ($this->value) {
            self::GREEN => 'Aktuell',
            self::YELLOW => 'Fällig bald',
            self::RED => 'Überfällig',
        };
    }

    /**
     * JSON-Serialization Support.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Validiert den Status-Wert.
     * 
     * @throws \InvalidArgumentException Bei ungültigem Status
     */
    private function validate(string $value): void
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            $validStatuses = implode(', ', self::VALID_STATUSES);
            throw new \InvalidArgumentException(
                "Invalid KPI status: {$value}"
            );
        }
    }
}