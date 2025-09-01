<?php

namespace App\Tests\Service;

use App\Service\DashboardService;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Service\KPIStatusService;
use App\Entity\User;
use App\Entity\KPI;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    public function testGetKpiDataForUserReturnsArray(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $user = $this->createMock(User::class);

        $kpiRepo->method('findByUser')->willReturn([]);
        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService);
        $result = $service->getKpiDataForUser($user);
        $this->assertIsArray($result);
    }

    public function testGetDashboardStatsReturnsCorrectStructure(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $user = $this->createMock(User::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi1->method('getName')->willReturn('Test KPI 1');
        $kpi1->method('getStatus')->willReturn('green');

        $kpiData = [
            ['name' => 'Test KPI 1', 'status' => 'green', 'due_date' => new \DateTime()],
            ['name' => 'Test KPI 2', 'status' => 'red', 'due_date' => new \DateTime('-1 day')],
        ];

        $kpiValueRepo->method('findRecentByUser')->willReturn([]);

        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService);
        $stats = $service->getDashboardStats($user, $kpiData);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_kpis', $stats);
        $this->assertArrayHasKey('overdue_count', $stats);
        $this->assertArrayHasKey('due_soon_count', $stats);
        $this->assertArrayHasKey('up_to_date_count', $stats);
        $this->assertEquals(2, $stats['total_kpis']);
    }
}
