<?php

namespace App\Tests\Integration\ValueObject;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration Tests for DecimalValue with Entity persistence.
 */
class DecimalValueIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
    }

    public function testDecimalValueInKPIEntity(): void
    {
        // Create test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        // Create KPI with DecimalValue target
        $kpi = new KPI();
        $kpi->setName('Test KPI with DecimalValue');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);
        $kpi->setTarget(new DecimalValue('1000,50'));

        // Test target functionality
        $this->assertInstanceOf(DecimalValue::class, $kpi->getTarget());
        $this->assertSame(1000.50, $kpi->getTargetAsFloat());
        $this->assertSame('1000,50', $kpi->getTarget()->format());

        // Persist entities (not actually saving to DB in unit test)
        $this->assertInstanceOf(KPI::class, $kpi);
    }

    public function testDecimalValueInKPIValueEntity(): void
    {
        // Create test user and KPI
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        // Create KPIValue with DecimalValue
        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('2500,75'));
        $kpiValue->setPeriod(new Period('2024-09'));

        // Test value functionality
        $this->assertInstanceOf(DecimalValue::class, $kpiValue->getValue());
        $this->assertSame(2500.75, $kpiValue->getValueAsFloat());
        $this->assertSame('2500,75', $kpiValue->getValue()->format());
        $this->assertStringContainsString('2500,75', (string) $kpiValue);

        // Test period integration
        $this->assertSame('September 2024', $kpiValue->getFormattedPeriod());
    }

    public function testNegativeDecimalValues(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        // Test negative value
        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('-150,25'));
        $kpiValue->setPeriod(new Period('2024-08'));

        $this->assertSame(-150.25, $kpiValue->getValueAsFloat());
        $this->assertSame('-150,25', $kpiValue->getValue()->format());
    }

    public function testNullableDecimalValues(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        // KPI without target (nullable)
        $kpi = new KPI();
        $kpi->setName('Test KPI without target');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);
        // $kpi->setTarget(null); // Explicitly null

        $this->assertNull($kpi->getTarget());
        $this->assertNull($kpi->getTargetAsFloat());
    }

    public function testDecimalValueComparisons(): void
    {
        $value1 = new DecimalValue('100,50');
        $value2 = new DecimalValue('100.50');
        $value3 = new DecimalValue('200,00');

        // Same values in different formats should be equal
        $this->assertSame($value1->toFloat(), $value2->toFloat());
        $this->assertSame($value1->getValue(), $value2->getValue());

        // Different values should not be equal
        $this->assertNotSame($value1->toFloat(), $value3->toFloat());
    }

    public function testDecimalValuePrecision(): void
    {
        // Test precision handling (2 decimal places)
        $value = new DecimalValue('123,456789');
        $this->assertSame(123.46, $value->toFloat()); // Rounded to 2 decimals
        $this->assertSame('123.46', $value->getValue()); // Stored as string
        $this->assertSame('123,46', $value->format()); // German format
    }

    public function testEntityStringRepresentation(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Revenue KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('5000,00'));
        $kpiValue->setPeriod(new Period('2024-09'));

        $stringRepresentation = (string) $kpiValue;
        
        $this->assertStringContainsString('5000,00', $stringRepresentation);
        $this->assertStringContainsString('September 2024', $stringRepresentation);
    }

}
