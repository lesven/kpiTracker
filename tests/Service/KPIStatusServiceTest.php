<?php

namespace App\Tests\Service;

use App\Service\KPIStatusService;
use App\Entity\KPI;
use App\Repository\KPIValueRepository;
use PHPUnit\Framework\TestCase;

class KPIStatusServiceTest extends TestCase
{
    public function testGetKpiStatusReturnsString(): void
    {
        $repo = $this->createMock(KPIValueRepository::class);
        $kpi = $this->createMock(KPI::class);
        $repo->method('findByKpiAndPeriod')->willReturn(null);
        $service = new KPIStatusService($repo);
        $result = $service->getKpiStatus($kpi);
        $this->assertIsString($result);
    }

    public function testGetKpiStatusReturnsGreenWhenValueExists(): void
    {
        $repo = $this->createMock(KPIValueRepository::class);
        $kpi = $this->createMock(KPI::class);
        $kpiValue = $this->createMock(\App\Entity\KPIValue::class);
        
        $kpi->method('getCurrentPeriod')->willReturn('2024-01');
        $repo->method('findByKpiAndPeriod')->willReturn($kpiValue);
        
        $service = new KPIStatusService($repo);
        $result = $service->getKpiStatus($kpi);
        $this->assertEquals('green', $result);
    }

    public function testGetKpiStatusReturnsRedWhenOverdue(): void
    {
        $repo = $this->createMock(KPIValueRepository::class);
        $kpi = $this->createMock(KPI::class);
        
        $kpi->method('getCurrentPeriod')->willReturn('2024-01');
        $kpi->method('getInterval')->willReturn('monthly');
        $repo->method('findByKpiAndPeriod')->willReturn(null);
        
        $service = new KPIStatusService($repo);
        $result = $service->getKpiStatus($kpi);
        $this->assertIsString($result);
        $this->assertContains($result, ['green', 'yellow', 'red']);
    }
}
