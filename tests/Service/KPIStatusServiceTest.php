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
}
