<?php

namespace App\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Value Object representing a KPI period.
 *
 * Supports formats:
 *  - YYYY-MM   (monthly)
 *  - YYYY-WXX  (weekly, e.g. 2024-W05)
 *  - YYYY-QX   (quarterly, e.g. 2024-Q1)
 */
#[ORM\Embeddable]
final class Period
{
    public const PATTERN = '/^(\d{4})-(\d{1,2}|W\d{1,2}|Q\d)$/';

    #[ORM\Column(name: 'period', length: 20)]
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException('Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX');
        }

        // Additional validation
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $matches)) {
            $month = (int) $matches[2];
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('Ungültiger Monat. Monate müssen zwischen 01 und 12 liegen.');
            }
        }

        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $value, $matches)) {
            $week = (int) $matches[2];
            if ($week < 1 || $week > 53) {
                throw new \InvalidArgumentException('Ungültige Woche. Wochen müssen zwischen 01 und 53 liegen.');
            }
        }

        if (preg_match('/^(\d{4})-Q(\d)$/', $value, $matches)) {
            $quarter = (int) $matches[2];
            if ($quarter < 1 || $quarter > 4) {
                throw new \InvalidArgumentException('Ungültiges Quartal. Quartale müssen zwischen 1 und 4 liegen.');
            }
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Formats the period for display (e.g. "Januar 2024", "KW 5/2024", "Q1 2024").
     */
    public function format(): string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $this->value, $matches)) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $monthNames = [
                '01' => 'Januar', '02' => 'Februar', '03' => 'März',
                '04' => 'April', '05' => 'Mai', '06' => 'Juni',
                '07' => 'Juli', '08' => 'August', '09' => 'September',
                '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
            ];

            return ($monthNames[$month] ?? 'Monat '.$month).' '.$year;
        }

        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $this->value, $matches)) {
            return 'KW '.ltrim($matches[2], '0').'/'.$matches[1];
        }

        if (preg_match('/^(\d{4})-Q(\d)$/', $this->value, $matches)) {
            return 'Q'.$matches[2].' '.$matches[1];
        }

        return $this->value;
    }

    /**
     * Compares this period with another period for equality.
     */
    public function equals(Period $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Creates a Period from a date and interval.
     */
    public static function fromDate(\DateTimeInterface $date, KpiInterval $interval): self
    {
        $periodString = match ($interval) {
            KpiInterval::WEEKLY => $date->format('Y-\WW'),
            KpiInterval::MONTHLY => $date->format('Y-m'),
            KpiInterval::QUARTERLY => $date->format('Y').'-Q'.ceil($date->format('n') / 3),
        };

        return new self($periodString);
    }

    /**
     * Creates a Period for the current date with the given interval.
     */
    public static function current(KpiInterval $interval): self
    {
        return self::fromDate(new \DateTimeImmutable(), $interval);
    }
}
