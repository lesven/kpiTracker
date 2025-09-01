<?php

namespace App\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Value Object for decimal numbers with validation and formatting.
 */
#[ORM\Embeddable]
final class DecimalValue
{
    #[ORM\Column(name: 'value', type: 'decimal', precision: 10, scale: 2)]
    private string $value;

    public function __construct(string $value)
    {
        $normalized = str_replace(',', '.', trim($value));
        
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('Ungültiger Dezimalwert "%s"', $value));
        }

        $float = (float) $normalized;
        $this->value = number_format($float, 2, '.', '');
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function format(): string
    {
        return number_format($this->toFloat(), 2, ',', '');
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
