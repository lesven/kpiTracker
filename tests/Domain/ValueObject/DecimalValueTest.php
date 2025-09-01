<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\DecimalValue;
use PHPUnit\Framework\TestCase;

class DecimalValueTest extends TestCase
{
    public function testValidDecimalWithComma(): void
    {
        $value = new DecimalValue('123,45');
        $this->assertSame(123.45, $value->toFloat());
        $this->assertSame('123,45', $value->format());
    }

    public function testValidDecimalWithDot(): void
    {
        $value = new DecimalValue('123.4');
        $this->assertSame(123.40, $value->toFloat());
        $this->assertSame('123,40', (string) $value);
    }

    public function testInvalidDecimalThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DecimalValue('abc');
    }
}
