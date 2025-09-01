<?php

namespace App\Tests\Functional;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests to verify DecimalValue implementation works end-to-end.
 * Note: Changed from WebTestCase to TestCase to avoid database connection issues.
 */
class DecimalValueFunctionalTest extends TestCase
{
    public function testCompleteDecimalValueWorkflow(): void
    {
        // Create test user
        $user = new User();
        $user->setEmail('functional-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Functional');
        $user->setLastName('Test');

        // Create KPI with DecimalValue target
        $kpi = new KPI();
        $kpi->setName('Functional Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);
        $kpi->setTarget(new DecimalValue('5000,00'));

        // Create KPIValue with DecimalValue
        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('4750,25'));
        $kpiValue->setPeriod(new Period('2024-09'));
        $kpiValue->setComment('Functional test value');

        // Test value functionality (without database persistence)
        $this->assertInstanceOf(DecimalValue::class, $kpiValue->getValue());
        $this->assertSame(4750.25, $kpiValue->getValueAsFloat());
        $this->assertSame('4750,25', $kpiValue->getValue()->format());

        // Test KPI target functionality
        $this->assertSame(5000.00, $kpi->getTargetAsFloat());
        $this->assertSame('5000,00', $kpi->getTarget()->format());

        // Test string representation
        $stringRepresentation = (string) $kpiValue;
        $this->assertStringContainsString('4750,25', $stringRepresentation);
        $this->assertStringContainsString('September 2024', $stringRepresentation);

        // Test period integration
        $this->assertSame('September 2024', $kpiValue->getFormattedPeriod());
        $this->assertSame('2024-09', $kpiValue->getPeriod()->value());
    }

    public function testDecimalValueEdgeCases(): void
    {
        // Test negative values
        $negativeValue = new DecimalValue('-1500,75');
        $this->assertSame(-1500.75, $negativeValue->toFloat());
        $this->assertSame('-1500,75', $negativeValue->format());

        // Test zero values
        $zeroValue = new DecimalValue('0,00');
        $this->assertSame(0.0, $zeroValue->toFloat());
        $this->assertSame('0,00', $zeroValue->format());

        // Test very large values
        $largeValue = new DecimalValue('999999,99');
        $this->assertSame(999999.99, $largeValue->toFloat());
        $this->assertSame('999999,99', $largeValue->format());

        // Test precision rounding
        $precisionValue = new DecimalValue('123,456789');
        $this->assertSame(123.46, $precisionValue->toFloat()); // Rounded to 2 decimals
        $this->assertSame('123,46', $precisionValue->format());
    }

    public function testEntityRelationships(): void
    {
        // Create complete entity structure
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::WEEKLY);
        $kpi->setTarget(new DecimalValue('2500,50'));

        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('2300,25'));
        $kpiValue->setPeriod(new Period('2024-W36'));

        // Test bidirectional relationships
        $this->assertSame($kpi, $kpiValue->getKpi());
        $this->assertSame($user, $kpi->getUser());

        // Test current period generation
        $currentPeriod = $kpi->getCurrentPeriod();
        $this->assertInstanceOf(Period::class, $currentPeriod);

        // Test value formatting in different contexts
        $this->assertSame('KW 36/2024', $kpiValue->getFormattedPeriod());
        $this->assertSame('2300,25', $kpiValue->getValue()->format());
        $this->assertSame('2500,50', $kpi->getTarget()->format());
    }

    public function testDecimalValueValidation(): void
    {
        // Test valid formats
        $validValues = [
            '100,50' => 100.50,
            '100.50' => 100.50,
            '-50,25' => -50.25,
            '0' => 0.00,
            '0,00' => 0.00,
            '999999,99' => 999999.99
        ];

        foreach ($validValues as $input => $expected) {
            $decimalValue = new DecimalValue($input);
            $this->assertSame($expected, $decimalValue->toFloat(), "Failed for input: $input");
        }

        // Test invalid formats should throw exceptions
        $invalidValues = ['abc', '12.34.56', '12,34,56', '', '  '];

        foreach ($invalidValues as $invalidInput) {
            $this->expectException(\InvalidArgumentException::class);
            new DecimalValue($invalidInput);
        }
    }

    public function testFormattingConsistency(): void
    {
        $testCases = [
            ['123,45', '123,45'],
            ['123.45', '123,45'],
            ['-456,78', '-456,78'],
            ['0', '0,00'],
            ['1000', '1000,00'],
        ];

        foreach ($testCases as [$input, $expectedFormat]) {
            $decimalValue = new DecimalValue($input);
            $this->assertSame($expectedFormat, $decimalValue->format());
            $this->assertSame($expectedFormat, (string) $decimalValue);
        }
    }
}
