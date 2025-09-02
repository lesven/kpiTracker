<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use PHPUnit\Framework\TestCase;

/**
 * Tests für das Period Value Object.
 */
class PeriodTest extends TestCase
{
    public function testConstructorWithValidPeriods(): void
    {
        $this->assertInstanceOf(Period::class, new Period('2024-01'));
        $this->assertInstanceOf(Period::class, new Period('2024-W05'));
        $this->assertInstanceOf(Period::class, new Period('2024-Q1'));
        $this->assertInstanceOf(Period::class, new Period('2024-12'));
        $this->assertInstanceOf(Period::class, new Period('2025-W52'));
        $this->assertInstanceOf(Period::class, new Period('2023-Q4'));
    }

    /**
     * @dataProvider invalidPeriodProvider
     */
    public function testConstructorWithInvalidPeriodsThrowsException(string $invalidPeriod, ?string $expectedMessage = null): void
    {
        $this->expectException(\InvalidArgumentException::class);
        if ($expectedMessage) {
            $this->expectExceptionMessage($expectedMessage);
        }

        new Period($invalidPeriod);
    }

    public static function invalidPeriodProvider(): array
    {
        return [
            ['', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'],
            ['2024', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'],
            ['2024-13', 'Ungültiger Monat. Monate müssen zwischen 01 und 12 liegen.'], // Invalid month
            ['2024-W', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'],  // Missing week number
            ['2024-W54', 'Ungültige Woche. Wochen müssen zwischen 01 und 53 liegen.'], // Invalid week (only 53 weeks max)
            ['2024-Q', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'],  // Missing quarter number
            ['2024-Q5', 'Ungültiges Quartal. Quartale müssen zwischen 1 und 4 liegen.'], // Invalid quarter
            ['24-01', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'],   // Wrong year format
            ['2024/01', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'], // Wrong separator
            ['invalid', 'Ungültiges Zeitraum-Format. Verwenden Sie: YYYY-MM, YYYY-WXX oder YYYY-QX'],
        ];
    }

    public function testFromString(): void
    {
        $period = Period::fromString('2024-03');
        $this->assertInstanceOf(Period::class, $period);
        $this->assertSame('2024-03', $period->value());
    }

    public function testToString(): void
    {
        $period = new Period('2024-05');
        $this->assertSame('2024-05', (string) $period);
    }

    public function testValue(): void
    {
        $period = new Period('2024-Q2');
        $this->assertSame('2024-Q2', $period->value());
    }

    public function testEquals(): void
    {
        $period1 = new Period('2024-01');
        $period2 = new Period('2024-01');
        $period3 = new Period('2024-02');

        $this->assertTrue($period1->equals($period2));
        $this->assertFalse($period1->equals($period3));
    }

    /**
     * @dataProvider formatTestProvider
     */
    public function testFormat(string $input, string $expected): void
    {
        $period = new Period($input);
        $this->assertSame($expected, $period->format());
    }

    public static function formatTestProvider(): array
    {
        return [
            // Monthly periods
            ['2024-01', 'Januar 2024'],
            ['2024-02', 'Februar 2024'],
            ['2024-03', 'März 2024'],
            ['2024-04', 'April 2024'],
            ['2024-05', 'Mai 2024'],
            ['2024-06', 'Juni 2024'],
            ['2024-07', 'Juli 2024'],
            ['2024-08', 'August 2024'],
            ['2024-09', 'September 2024'],
            ['2024-10', 'Oktober 2024'],
            ['2024-11', 'November 2024'],
            ['2024-12', 'Dezember 2024'],

            // Weekly periods
            ['2024-W01', 'KW 1/2024'],
            ['2024-W05', 'KW 5/2024'],
            ['2024-W52', 'KW 52/2024'],

            // Quarterly periods
            ['2024-Q1', 'Q1 2024'],
            ['2024-Q2', 'Q2 2024'],
            ['2024-Q3', 'Q3 2024'],
            ['2024-Q4', 'Q4 2024'],

            // Unknown format fallback
            // NOTE: Removed '2024-unknown' test as it would throw an exception
        ];
    }

    public function testFromDateWithDifferentIntervals(): void
    {
        $date = new \DateTimeImmutable('2024-03-15');

        // Monthly - PHP format 'Y-m' gives '2024-03'
        $monthlyPeriod = Period::fromDate($date, KpiInterval::MONTHLY);
        $this->assertSame('2024-03', $monthlyPeriod->value());

        // Weekly (Week 11 in 2024)
        $weeklyPeriod = Period::fromDate($date, KpiInterval::WEEKLY);
        $this->assertSame('2024-W11', $weeklyPeriod->value());

        // Quarterly (Q1)
        $quarterlyPeriod = Period::fromDate($date, KpiInterval::QUARTERLY);
        $this->assertSame('2024-Q1', $quarterlyPeriod->value());
    }

    public function testFromDateQuarterCalculation(): void
    {
        // Test all quarters
        $q1Date = new \DateTimeImmutable('2024-02-15'); // February = Q1
        $q1Period = Period::fromDate($q1Date, KpiInterval::QUARTERLY);
        $this->assertSame('2024-Q1', $q1Period->value());

        $q2Date = new \DateTimeImmutable('2024-05-15'); // May = Q2
        $q2Period = Period::fromDate($q2Date, KpiInterval::QUARTERLY);
        $this->assertSame('2024-Q2', $q2Period->value());

        $q3Date = new \DateTimeImmutable('2024-08-15'); // August = Q3
        $q3Period = Period::fromDate($q3Date, KpiInterval::QUARTERLY);
        $this->assertSame('2024-Q3', $q3Period->value());

        $q4Date = new \DateTimeImmutable('2024-11-15'); // November = Q4
        $q4Period = Period::fromDate($q4Date, KpiInterval::QUARTERLY);
        $this->assertSame('2024-Q4', $q4Period->value());
    }

    public function testCurrent(): void
    {
        // Note: This test is time-sensitive and might need adjustment
        $currentMonthly = Period::current(KpiInterval::MONTHLY);
        $currentWeekly = Period::current(KpiInterval::WEEKLY);
        $currentQuarterly = Period::current(KpiInterval::QUARTERLY);

        // Verify they return Period objects
        $this->assertInstanceOf(Period::class, $currentMonthly);
        $this->assertInstanceOf(Period::class, $currentWeekly);
        $this->assertInstanceOf(Period::class, $currentQuarterly);

        // Verify format patterns
        $this->assertMatchesRegularExpression('/^\d{4}-\d{1,2}$/', $currentMonthly->value());
        $this->assertMatchesRegularExpression('/^\d{4}-W\d{1,2}$/', $currentWeekly->value());
        $this->assertMatchesRegularExpression('/^\d{4}-Q\d$/', $currentQuarterly->value());
    }

    public function testPeriodPattern(): void
    {
        // Test the regex pattern directly
        $this->assertSame(1, preg_match(Period::PATTERN, '2024-01'));
        $this->assertSame(1, preg_match(Period::PATTERN, '2024-1'));
        $this->assertSame(1, preg_match(Period::PATTERN, '2024-W05'));
        $this->assertSame(1, preg_match(Period::PATTERN, '2024-Q1'));

        $this->assertSame(0, preg_match(Period::PATTERN, 'invalid'));
        $this->assertSame(0, preg_match(Period::PATTERN, '24-01'));
    }

    public function testImmutability(): void
    {
        $original = new Period('2024-01');
        $copy = Period::fromString($original->value());

        $this->assertTrue($original->equals($copy));
        $this->assertNotSame($original, $copy); // Different instances
    }

    public function testJsonSerialization(): void
    {
        $period = new Period('2024-Q2');
        $json = json_encode(['period' => (string) $period]);
        $this->assertSame('{"period":"2024-Q2"}', $json);
    }

    public function testEdgeCases(): void
    {
        // Test edge cases for month formatting
        $period = new Period('2024-01');
        $this->assertSame('Januar 2024', $period->format());

        // Test week with leading zero removal
        $period = new Period('2024-W01');
        $this->assertSame('KW 1/2024', $period->format());

        // Test single digit month
        $period = new Period('2024-3');
        $this->assertSame('März 2024', $period->format());
    }
}
