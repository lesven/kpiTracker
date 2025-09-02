<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Service\KPIAggregate;
use App\Service\KPIService;
use PHPUnit\Framework\TestCase;

class KPIServiceTest extends TestCase
{
    public function testGetKpiStatusDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $aggregate->expects($this->once())
            ->method('getKpiStatus')
            ->with($kpi)
            ->willReturn('green');

        $service = new KPIService($aggregate);
        $result = $service->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testHasCurrentValueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $aggregate->expects($this->once())
            ->method('hasCurrentValue')
            ->with($kpi)
            ->willReturn(true);

        $service = new KPIService($aggregate);
        $result = $service->hasCurrentValue($kpi);
        $this->assertTrue($result);
    }

    public function testGetKpiStatisticsDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedStats = ['total_entries' => 0];
        $aggregate->expects($this->once())
            ->method('getKpiStatistics')
            ->with($kpi)
            ->willReturn($expectedStats);

        $service = new KPIService($aggregate);
        $result = $service->getKpiStatistics($kpi);
        $this->assertSame($expectedStats, $result);
    }

    public function testValidateKpiDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedErrors = ['error1'];
        $aggregate->expects($this->once())
            ->method('validateKpi')
            ->with($kpi)
            ->willReturn($expectedErrors);

        $service = new KPIService($aggregate);
        $result = $service->validateKpi($kpi);
        $this->assertSame($expectedErrors, $result);
    }
}
