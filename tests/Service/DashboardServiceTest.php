<?php

namespace App\Tests\Service;

use App\DTO\DashboardKpiEntry;
use App\Entity\KPI;
use App\Entity\User;
use App\Factory\DashboardKpiEntryFactory;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Service\DashboardService;
use App\Service\KPIStatusService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    public function testGetKpiDataForUserReturnsArray(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $factory = $this->createMock(DashboardKpiEntryFactory::class);
        $user = $this->createMock(User::class);

        $kpiRepo->method('findByUser')->willReturn([]);
        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService, $factory);
        $result = $service->getKpiDataForUser($user);
        $this->assertIsArray($result);
    }

    public function testGetDashboardStatsReturnsCorrectStructure(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $factory = $this->createMock(DashboardKpiEntryFactory::class);
        $user = $this->createMock(User::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi2 = $this->createMock(KPI::class);

        $kpiData = [
            new DashboardKpiEntry($kpi1, 'green', null, false, false, new \DateTime()),
            new DashboardKpiEntry($kpi2, 'red', null, false, true, new \DateTime('-1 day')),
        ];

        $kpiValueRepo->method('findRecentByUser')->willReturn([]);

        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService, $factory);
        $stats = $service->getDashboardStats($user, $kpiData);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_kpis', $stats);
        $this->assertArrayHasKey('overdue_count', $stats);
        $this->assertArrayHasKey('due_soon_count', $stats);
        $this->assertArrayHasKey('up_to_date_count', $stats);
        $this->assertSame(2, $stats['total_kpis']);
    }
}
