<?php

namespace App\Tests\Service;

use App\Service\KPIService;
use App\Entity\KPI;
use App\Repository\KPIValueRepository;
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
        $this->assertEquals('green', $result);
    }
}
