<?php

namespace App\Tests\Factory;

use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Factory\KPIValueFactory;
use PHPUnit\Framework\TestCase;

class KPIValueFactoryTest extends TestCase
{
    public function testCreateWithExplicitPeriod(): void
    {
        $kpi = $this->createMock(KPI::class);
        $period = new Period('2025-09');
        $factory = new KPIValueFactory();

        $kpiValue = $factory->create($kpi, $period);

        $this->assertInstanceOf(KPIValue::class, $kpiValue);
        $this->assertSame($kpi, $kpiValue->getKpi());
        $this->assertSame($period, $kpiValue->getPeriod());
        $this->assertInstanceOf(\DateTimeImmutable::class, $kpiValue->getCreatedAt());
        $this->assertNull($kpiValue->getUpdatedAt());
    }

    public function testCreateWithDefaultPeriod(): void
    {
        $period = new Period('2025-09');
        $kpi = $this->createMock(KPI::class);
        $kpi->method('getCurrentPeriod')->willReturn($period);
        $factory = new KPIValueFactory();

        $kpiValue = $factory->create($kpi);

        $this->assertInstanceOf(KPIValue::class, $kpiValue);
        $this->assertSame($kpi, $kpiValue->getKpi());
        $this->assertSame($period, $kpiValue->getPeriod());
        $this->assertInstanceOf(\DateTimeImmutable::class, $kpiValue->getCreatedAt());
        $this->assertNull($kpiValue->getUpdatedAt());
    }
}
