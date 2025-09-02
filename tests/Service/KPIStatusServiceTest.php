<?php

namespace App\Tests\Service;

use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use App\Service\KPIStatusService;
use PHPUnit\Framework\TestCase;

class KPIStatusServiceTest extends TestCase
{
    public function testGetKpiStatusReturnsString(): void
    {
        $repo = $this->createMock(KPIValueRepository::class);
        $kpi = $this->createMock(KPI::class);
        $currentPeriod = new Period('2024-01');
        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $repo->method('findByKpiAndPeriod')->willReturn(null);
        $service = new KPIStatusService($repo);
        $result = $service->getKpiStatus($kpi);
        $this->assertIsString($result);
    }

    public function testGetKpiStatusReturnsGreenWhenValueExists(): void
    {
        $repo = $this->createMock(KPIValueRepository::class);
        $kpi = $this->createMock(KPI::class);
        $kpiValue = $this->createMock(KPIValue::class);

        $currentPeriod = new Period('2024-01');
        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $repo->method('findByKpiAndPeriod')->willReturn($kpiValue);

        $service = new KPIStatusService($repo);
        $result = $service->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testGetKpiStatusReturnsRedWhenOverdue(): void
    {
        $repo = $this->createMock(KPIValueRepository::class);
        $kpi = $this->createMock(KPI::class);

        $currentPeriod = new Period('2024-01');
        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $kpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $repo->method('findByKpiAndPeriod')->willReturn(null);

        $service = new KPIStatusService($repo);
        $result = $service->getKpiStatus($kpi);
        $this->assertIsString($result);
        $this->assertContains($result, ['green', 'yellow', 'red']);
    }
}
