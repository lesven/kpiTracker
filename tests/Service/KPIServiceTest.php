<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Repository\KPIValueRepository;
use App\Service\KPIService;
use App\Service\KPIStatusService;
use PHPUnit\Framework\TestCase;

class KPIServiceTest extends TestCase
{
    public function testGetKpiStatusDelegatesToStatusService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $repo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $statusService->expects($this->once())
            ->method('getKpiStatus')
            ->with($kpi)
            ->willReturn('green');
        $service = new KPIService($repo, $statusService);
        $result = $service->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testHasCurrentValueReturnsTrueWhenValueExists(): void
    {
        $kpi = $this->createMock(KPI::class);
        $repo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);

        $kpiValue = $this->createMock(\App\Entity\KPIValue::class);
        $kpi->method('getCurrentPeriod')->willReturn('2024-01');
        $repo->method('findByKpiAndPeriod')->willReturn($kpiValue);

        $service = new KPIService($repo, $statusService);
        $result = $service->hasCurrentValue($kpi);
        $this->assertTrue($result);
    }

    public function testGetKpiStatisticsReturnsArray(): void
    {
        $kpi = $this->createMock(KPI::class);
        $repo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);

        $repo->method('findByKPI')->willReturn([]);

        $service = new KPIService($repo, $statusService);
        $result = $service->getKpiStatistics($kpi);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_entries', $result);
    }
}
