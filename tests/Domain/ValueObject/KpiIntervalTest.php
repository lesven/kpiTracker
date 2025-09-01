<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\KpiInterval;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für das KpiInterval Value Object.
 *
 * Testet alle Methoden und Edge Cases des KpiInterval Enums.
 */
class KpiIntervalTest extends TestCase
{
    /**
     * Testet die verfügbaren Enum-Cases.
     */
    public function testEnumCasesAreAvailable(): void
    {
        $this->assertSame('weekly', KpiInterval::WEEKLY->value);
        $this->assertSame('monthly', KpiInterval::MONTHLY->value);
        $this->assertSame('quarterly', KpiInterval::QUARTERLY->value);
    }

    /**
     * Testet die fromString() Factory-Methode mit gültigen Werten.
     */
    public function testFromStringWithValidValues(): void
    {
        $this->assertSame(KpiInterval::WEEKLY, KpiInterval::fromString('weekly'));
        $this->assertSame(KpiInterval::MONTHLY, KpiInterval::fromString('monthly'));
        $this->assertSame(KpiInterval::QUARTERLY, KpiInterval::fromString('quarterly'));
    }

    /**
     * Testet die fromString() Factory-Methode mit ungültigen Werten.
     */
    public function testFromStringWithInvalidValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid KPI interval "invalid"');

        KpiInterval::fromString('invalid');
    }

    /**
     * Testet weitere ungültige Werte für fromString().
     *
     * @dataProvider invalidIntervalProvider
     */
    public function testFromStringWithVariousInvalidValues(string $invalidValue): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid KPI interval/');

        KpiInterval::fromString($invalidValue);
    }

    /**
     * Data Provider für ungültige Interval-Werte.
     */
    public static function invalidIntervalProvider(): array
    {
        return [
            [''],
            ['daily'],
            ['yearly'],
            ['WEEKLY'], // Groß-/Kleinschreibung
            ['Monthly'],
            ['QUARTERLY'],
            ['week'],
            ['month'],
            ['quarter'],
            ['null'],
            ['0'],
            ['false'],
        ];
    }

    /**
     * Testet die label() Methode für alle Enum-Cases.
     */
    public function testLabelReturnsCorrectGermanLabels(): void
    {
        $this->assertSame('Wöchentlich', KpiInterval::WEEKLY->label());
        $this->assertSame('Monatlich', KpiInterval::MONTHLY->label());
        $this->assertSame('Quartalsweise', KpiInterval::QUARTERLY->label());
    }

    /**
     * Testet, dass alle Enum-Cases korrekte Labels haben.
     */
    public function testAllCasesHaveLabels(): void
    {
        $cases = KpiInterval::cases();

        foreach ($cases as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
            $this->assertNotSame($case->value, $label); // Label sollte nicht gleich dem Wert sein
        }
    }

    /**
     * Testet die Serialisierung des Enums zu String.
     */
    public function testEnumToString(): void
    {
        $this->assertSame('weekly', (string) KpiInterval::WEEKLY->value);
        $this->assertSame('monthly', (string) KpiInterval::MONTHLY->value);
        $this->assertSame('quarterly', (string) KpiInterval::QUARTERLY->value);
    }

    /**
     * Testet die Enum-Vergleiche.
     */
    public function testEnumComparison(): void
    {
        $weekly1 = KpiInterval::WEEKLY;
        $weekly2 = KpiInterval::WEEKLY;
        $monthly = KpiInterval::MONTHLY;

        $this->assertTrue($weekly1 === $weekly2);
        $this->assertFalse($weekly1 === $monthly);
        $this->assertTrue($weekly1 !== $monthly);
    }

    /**
     * Testet die tryFrom() Methode.
     */
    public function testTryFromWithValidValues(): void
    {
        $this->assertSame(KpiInterval::WEEKLY, KpiInterval::tryFrom('weekly'));
        $this->assertSame(KpiInterval::MONTHLY, KpiInterval::tryFrom('monthly'));
        $this->assertSame(KpiInterval::QUARTERLY, KpiInterval::tryFrom('quarterly'));
    }

    /**
     * Testet die tryFrom() Methode mit ungültigen Werten.
     */
    public function testTryFromWithInvalidValues(): void
    {
        $this->assertNull(KpiInterval::tryFrom('invalid'));
        $this->assertNull(KpiInterval::tryFrom(''));
        $this->assertNull(KpiInterval::tryFrom('daily'));
        $this->assertNull(KpiInterval::tryFrom('WEEKLY'));
    }

    /**
     * Testet die cases() Methode.
     */
    public function testCasesReturnsAllEnumValues(): void
    {
        $cases = KpiInterval::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(KpiInterval::WEEKLY, $cases);
        $this->assertContains(KpiInterval::MONTHLY, $cases);
        $this->assertContains(KpiInterval::QUARTERLY, $cases);
    }

    /**
     * Testet die Verwendung in Arrays.
     */
    public function testEnumInArrays(): void
    {
        $intervals = [
            KpiInterval::WEEKLY,
            KpiInterval::MONTHLY,
            KpiInterval::QUARTERLY,
        ];

        $this->assertContains(KpiInterval::WEEKLY, $intervals);
        $this->assertNotContains(KpiInterval::tryFrom('invalid'), $intervals);
    }

    /**
     * Testet die Verwendung in match-Expressions.
     */
    public function testEnumInMatchExpressions(): void
    {
        $result = match (KpiInterval::WEEKLY) {
            KpiInterval::WEEKLY => 'weekly_result',
            KpiInterval::MONTHLY => 'monthly_result',
            KpiInterval::QUARTERLY => 'quarterly_result',
        };

        $this->assertSame('weekly_result', $result);

        $result = match (KpiInterval::QUARTERLY) {
            KpiInterval::WEEKLY => 'weekly_result',
            KpiInterval::MONTHLY => 'monthly_result',
            KpiInterval::QUARTERLY => 'quarterly_result',
        };

        $this->assertSame('quarterly_result', $result);
    }

    /**
     * Testet die JSON-Serialisierung.
     */
    public function testJsonSerialization(): void
    {
        $data = [
            'interval' => KpiInterval::WEEKLY->value,
            'label' => KpiInterval::WEEKLY->label(),
        ];

        $json = json_encode($data);
        $decoded = json_decode($json, true);

        $this->assertSame('weekly', $decoded['interval']);
        $this->assertSame('Wöchentlich', $decoded['label']);
    }

    /**
     * Testet die Eindeutigkeit der Enum-Werte.
     */
    public function testEnumValuesAreUnique(): void
    {
        $values = array_map(fn ($case) => $case->value, KpiInterval::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues);
    }

    /**
     * Testet die Eindeutigkeit der Labels.
     */
    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn ($case) => $case->label(), KpiInterval::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels);
    }
}
