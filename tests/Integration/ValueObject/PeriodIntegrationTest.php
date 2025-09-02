<?php

namespace App\Tests\Integration\ValueObject;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Period Value Object.
 * Tests Period integration with other system components.
 */
class PeriodIntegrationTest extends TestCase
{
    public function testPeriodIntegrationWithKpiValue(): void
    {
        // Create test entities
        $user = new User();
        $user->setEmail('period-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Period');
        $user->setLastName('Test');

        $kpi = new KPI();
        $kpi->setName('Period Integration KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        // Test Period integration with KPIValue
        $period = new Period('2024-09');
        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('1500,50'));
        $kpiValue->setPeriod($period);

        // Test Period methods
        $this->assertSame('2024-09', $kpiValue->getPeriod()->value());
        $this->assertSame('September 2024', $kpiValue->getFormattedPeriod());
        $this->assertSame('2024-09', (string) $kpiValue->getPeriod());
    }

    public function testPeriodWithDifferentIntervals(): void
    {
        $user = new User();
        $user->setEmail('interval-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Interval');
        $user->setLastName('Test');

        // Test monthly KPI with monthly period
        $monthlyKpi = new KPI();
        $monthlyKpi->setName('Monthly KPI');
        $monthlyKpi->setUser($user);
        $monthlyKpi->setInterval(KpiInterval::MONTHLY);

        $monthlyValue = new KPIValue();
        $monthlyValue->setKpi($monthlyKpi);
        $monthlyValue->setValue(new DecimalValue('1000'));
        $monthlyValue->setPeriod(new Period('2024-06'));

        $this->assertSame('Juni 2024', $monthlyValue->getFormattedPeriod());

        // Test weekly KPI with weekly period
        $weeklyKpi = new KPI();
        $weeklyKpi->setName('Weekly KPI');
        $weeklyKpi->setUser($user);
        $weeklyKpi->setInterval(KpiInterval::WEEKLY);

        $weeklyValue = new KPIValue();
        $weeklyValue->setKpi($weeklyKpi);
        $weeklyValue->setValue(new DecimalValue('250'));
        $weeklyValue->setPeriod(new Period('2024-W25'));

        $this->assertSame('KW 25/2024', $weeklyValue->getFormattedPeriod());

        // Test quarterly KPI with quarterly period
        $quarterlyKpi = new KPI();
        $quarterlyKpi->setName('Quarterly KPI');
        $quarterlyKpi->setUser($user);
        $quarterlyKpi->setInterval(KpiInterval::QUARTERLY);

        $quarterlyValue = new KPIValue();
        $quarterlyValue->setKpi($quarterlyKpi);
        $quarterlyValue->setValue(new DecimalValue('5000'));
        $quarterlyValue->setPeriod(new Period('2024-Q2'));

        $this->assertSame('Q2 2024', $quarterlyValue->getFormattedPeriod());
    }

    public function testPeriodCurrentGeneration(): void
    {
        $user = new User();
        $user->setEmail('current-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Current');
        $user->setLastName('Test');

        // Test current period generation for different intervals
        $monthlyKpi = new KPI();
        $monthlyKpi->setName('Monthly KPI');
        $monthlyKpi->setUser($user);
        $monthlyKpi->setInterval(KpiInterval::MONTHLY);

        $currentPeriod = $monthlyKpi->getCurrentPeriod();
        $this->assertInstanceOf(Period::class, $currentPeriod);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{1,2}$/', $currentPeriod->value());

        // Test weekly interval
        $weeklyKpi = new KPI();
        $weeklyKpi->setName('Weekly KPI');
        $weeklyKpi->setUser($user);
        $weeklyKpi->setInterval(KpiInterval::WEEKLY);

        $currentWeeklyPeriod = $weeklyKpi->getCurrentPeriod();
        $this->assertInstanceOf(Period::class, $currentWeeklyPeriod);
        $this->assertMatchesRegularExpression('/^\d{4}-W\d{1,2}$/', $currentWeeklyPeriod->value());

        // Test quarterly interval
        $quarterlyKpi = new KPI();
        $quarterlyKpi->setName('Quarterly KPI');
        $quarterlyKpi->setUser($user);
        $quarterlyKpi->setInterval(KpiInterval::QUARTERLY);

        $currentQuarterlyPeriod = $quarterlyKpi->getCurrentPeriod();
        $this->assertInstanceOf(Period::class, $currentQuarterlyPeriod);
        $this->assertMatchesRegularExpression('/^\d{4}-Q[1-4]$/', $currentQuarterlyPeriod->value());
    }

    public function testPeriodComparison(): void
    {
        $period1 = new Period('2024-09');
        $period2 = new Period('2024-09');
        $period3 = new Period('2024-10');

        // Test equality
        $this->assertTrue($period1->equals($period2));
        $this->assertFalse($period1->equals($period3));

        // Test different period types
        $monthlyPeriod = new Period('2024-09');
        $weeklyPeriod = new Period('2024-W36');
        $quarterlyPeriod = new Period('2024-Q3');

        $this->assertFalse($monthlyPeriod->equals($weeklyPeriod));
        $this->assertFalse($monthlyPeriod->equals($quarterlyPeriod));
        $this->assertFalse($weeklyPeriod->equals($quarterlyPeriod));
    }

    public function testPeriodSerialization(): void
    {
        $period = new Period('2024-Q2');
        
        // Test JSON serialization
        $json = json_encode(['period' => (string) $period]);
        $this->assertSame('{"period":"2024-Q2"}', $json);

        // Test array serialization
        $data = [
            'value' => $period->value(),
            'formatted' => $period->format(),
        ];

        $this->assertSame('2024-Q2', $data['value']);
        $this->assertSame('Q2 2024', $data['formatted']);
    }

    public function testPeriodFromDateIntegration(): void
    {
        $testDate = new \DateTimeImmutable('2024-09-15');

        // Test all intervals
        $monthlyPeriod = Period::fromDate($testDate, KpiInterval::MONTHLY);
        $this->assertSame('2024-09', $monthlyPeriod->value());

        $weeklyPeriod = Period::fromDate($testDate, KpiInterval::WEEKLY);
        $this->assertMatchesRegularExpression('/^2024-W\d{1,2}$/', $weeklyPeriod->value());

        $quarterlyPeriod = Period::fromDate($testDate, KpiInterval::QUARTERLY);
        $this->assertSame('2024-Q3', $quarterlyPeriod->value()); // September is Q3
    }

    public function testPeriodValidationIntegration(): void
    {
        // Test that invalid periods are properly rejected when used with entities
        $user = new User();
        $user->setEmail('validation-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Validation');
        $user->setLastName('Test');

        $kpi = new KPI();
        $kpi->setName('Validation KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('1000'));

        // Test that invalid period construction throws exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiges Zeitraum-Format');
        
        $invalidPeriod = new Period('invalid-period');
        $kpiValue->setPeriod($invalidPeriod);
    }
}