<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

class EmailAddressTest extends TestCase
{
    public function testItNormalizesAndValidates(): void
    {
        $email = new EmailAddress(' Test@Example.COM ');
        $this->assertSame('test@example.com', $email->getValue());
        $this->assertSame('test@example.com', (string) $email);
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EmailAddress('invalid-email');
    }
}
