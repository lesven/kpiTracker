<?php

namespace App\Domain\ValueObject;

/**
 * Value Object for KPI intervals.
 */
enum KpiInterval: string
{
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';

    /**
     * @throws \InvalidArgumentException if the value is not a valid interval
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new \InvalidArgumentException(sprintf('Invalid KPI interval "%s"', $value));
    }

    /**
     * Human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Wöchentlich',
            self::MONTHLY => 'Monatlich',
            self::QUARTERLY => 'Quartalsweise',
        };
    }
}
