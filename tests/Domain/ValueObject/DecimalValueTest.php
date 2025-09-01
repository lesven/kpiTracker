<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\DecimalValue;
use PHPUnit\Framework\TestCase;

class DecimalValueTest extends TestCase
{
    /**
     * Testet gültige Dezimalzahlen mit Komma.
     */
    public function testValidDecimalWithComma(): void
    {
        $value = new DecimalValue('123,45');
        $this->assertSame(123.45, $value->toFloat());
        $this->assertSame('123,45', $value->format());
    }

    /**
     * Testet gültige Dezimalzahlen mit Punkt.
     */
    public function testValidDecimalWithDot(): void
    {
        $value = new DecimalValue('123.40');
        $this->assertSame(123.40, $value->toFloat());
        $this->assertSame('123,40', $value->format());
        $this->assertSame('123,40', (string) $value);
    }

    /**
     * Testet ungültige Dezimalzahlen.
     */
    public function testInvalidDecimalThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger Dezimalwert "abc"');
        new DecimalValue('abc');
    }

    /**
     * Testet negative Zahlen.
     */
    public function testNegativeNumbers(): void
    {
        $value = new DecimalValue('-456,78');
        $this->assertSame(-456.78, $value->toFloat());
        $this->assertSame('-456,78', $value->format());
        
        $value2 = new DecimalValue('-100.50');
        $this->assertSame(-100.50, $value2->toFloat());
        $this->assertSame('-100,50', $value2->format());
    }

    /**
     * Testet Ganzzahlen.
     */
    public function testWholeNumbers(): void
    {
        $value = new DecimalValue('1000');
        $this->assertSame(1000.00, $value->toFloat());
        $this->assertSame('1000,00', $value->format());
        $this->assertSame('1000.00', $value->getValue());
    }

    /**
     * Testet Null-Werte.
     */
    public function testZeroValues(): void
    {
        $value = new DecimalValue('0');
        $this->assertSame(0.00, $value->toFloat());
        $this->assertSame('0,00', $value->format());
        
        $value2 = new DecimalValue('0,00');
        $this->assertSame(0.00, $value2->toFloat());
        $this->assertSame('0,00', $value2->format());
    }

    /**
     * Testet sehr kleine Zahlen.
     */
    public function testVerySmallNumbers(): void
    {
        $value = new DecimalValue('0,01');
        $this->assertSame(0.01, $value->toFloat());
        $this->assertSame('0,01', $value->format());
    }

    /**
     * Testet sehr große Zahlen.
     */
    public function testVeryLargeNumbers(): void
    {
        $value = new DecimalValue('999999,99');
        $this->assertSame(999999.99, $value->toFloat());
        $this->assertSame('999999,99', $value->format());
    }

    /**
     * Testet Zahlen mit führenden/nachfolgenden Leerzeichen.
     */
    public function testTrimming(): void
    {
        $value = new DecimalValue('  123,45  ');
        $this->assertSame(123.45, $value->toFloat());
        $this->assertSame('123,45', $value->format());
    }

    /**
     * Testet Factory-Methode.
     */
    public function testFromStringFactory(): void
    {
        $value = DecimalValue::fromString('789,12');
        $this->assertInstanceOf(DecimalValue::class, $value);
        $this->assertSame(789.12, $value->toFloat());
    }

    /**
     * Testet __toString() Methode.
     */
    public function testToString(): void
    {
        $value = new DecimalValue('250,75');
        $this->assertSame('250,75', (string) $value);
    }

    /**
     * Testet getValue() für Doctrine.
     */
    public function testGetValueForDoctrine(): void
    {
        $value = new DecimalValue('199,99');
        $this->assertSame('199.99', $value->getValue());
    }

    /**
     * @dataProvider invalidValueProvider
     */
    public function testInvalidValues(string $invalidValue): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Ungültiger Dezimalwert/');
        new DecimalValue($invalidValue);
    }

    /**
     * Provider für ungültige Werte.
     */
    public static function invalidValueProvider(): array
    {
        return [
            [''],
            ['abc'],
            ['12.34.56'],
            ['12,34,56'],
            ['12.3.4'],
            ['hello123'],
            ['12a34'],
            ['..'],
            [',,'],
            [' '],
        ];
    }

    /**
     * @dataProvider validValueProvider
     */
    public function testValidValues(string $input, float $expectedFloat, string $expectedFormat): void
    {
        $value = new DecimalValue($input);
        $this->assertSame($expectedFloat, $value->toFloat());
        $this->assertSame($expectedFormat, $value->format());
    }

    /**
     * Provider für gültige Werte.
     */
    public static function validValueProvider(): array
    {
        return [
            ['123', 123.00, '123,00'],
            ['123,45', 123.45, '123,45'],
            ['123.45', 123.45, '123,45'],
            ['-123,45', -123.45, '-123,45'],
            ['-123.45', -123.45, '-123,45'],
            ['0', 0.00, '0,00'],
            ['0,00', 0.00, '0,00'],
            ['0.00', 0.00, '0,00'],
            ['1000000', 1000000.00, '1000000,00'],
            ['0,01', 0.01, '0,01'],
            ['999999,99', 999999.99, '999999,99'],
        ];
    }

    /**
     * Testet Präzision (2 Dezimalstellen).
     */
    public function testPrecision(): void
    {
        $value = new DecimalValue('123,456789');
        $this->assertSame(123.46, $value->toFloat()); // Rounded to 2 decimals
        $this->assertSame('123,46', $value->format());
    }

    /**
     * Testet Immutability.
     */
    public function testImmutability(): void
    {
        $value1 = new DecimalValue('100,50');
        $value2 = DecimalValue::fromString('100,50');
        
        $this->assertNotSame($value1, $value2); // Different instances
        $this->assertSame($value1->getValue(), $value2->getValue()); // Same value
    }

    /**
     * Testet JSON-Serialisierung.
     */
    public function testJsonSerialization(): void
    {
        $value = new DecimalValue('1234,56');
        
        // Test direct serialization
        $data = [
            'value_float' => $value->toFloat(),
            'value_formatted' => $value->format(),
            'value_raw' => $value->getValue(),
        ];
        
        $json = json_encode($data);
        $decoded = json_decode($json, true);
        
        $this->assertSame(1234.56, $decoded['value_float']);
        $this->assertSame('1234,56', $decoded['value_formatted']);
        $this->assertSame('1234.56', $decoded['value_raw']);
    }

    /**
     * Testet Boundary Values.
     */
    public function testBoundaryValues(): void
    {
        // Test smallest positive value
        $small = new DecimalValue('0,01');
        $this->assertSame(0.01, $small->toFloat());
        
        // Test zero
        $zero = new DecimalValue('0');
        $this->assertSame(0.00, $zero->toFloat());
        
        // Test largest reasonable value (within decimal precision)
        $large = new DecimalValue('9999999,99');
        $this->assertSame(9999999.99, $large->toFloat());
    }
}
