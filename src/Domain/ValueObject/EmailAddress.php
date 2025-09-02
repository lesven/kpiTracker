<?php

namespace App\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Value Object representing a validated and normalized email address.
 */
#[ORM\Embeddable]
final class EmailAddress
{
    #[ORM\Column(name: 'email', type: 'string', length: 180, unique: true)]
    private string $value;

    public function __construct(string $email)
    {
        $normalized = mb_strtolower(trim($email));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Ungültige E-Mail-Adresse "%s"', $email));
        }

        $this->value = $normalized;
    }

    public static function fromString(string $email): self
    {
        return new self($email);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
