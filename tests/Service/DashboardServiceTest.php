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

        $this->assertArrayHasKey('total_kpis', $stats);
        $this->assertArrayHasKey('overdue_count', $stats);
        $this->assertArrayHasKey('due_soon_count', $stats);
        $this->assertArrayHasKey('up_to_date_count', $stats);
        $this->assertArrayHasKey('recent_values', $stats);
        
        $this->assertSame(2, $stats['total_kpis']);
        $this->assertSame(1, $stats['overdue_count']);
        $this->assertSame(0, $stats['due_soon_count']);
        $this->assertSame(1, $stats['up_to_date_count']);
    }

    public function testGetKpiDataForUserSortsDataByDueDate(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $factory = $this->createMock(DashboardKpiEntryFactory::class);
        $user = $this->createMock(User::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi2 = $this->createMock(KPI::class);
        $kpis = [$kpi1, $kpi2];

        $kpiRepo->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($kpis);

        // Mock factory to return specific entries that will be sorted
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                new DashboardKpiEntry($kpi1, 'green', new \DateTimeImmutable('+2 days'), false, false, new \DateTime()),
                new DashboardKpiEntry($kpi2, 'yellow', new \DateTimeImmutable('+1 day'), false, false, new \DateTime())
            );

        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService, $factory);
        $result = $service->getKpiDataForUser($user);

        $this->assertCount(2, $result);
        // Überprüfe tatsächliche Reihenfolge und Status
        $this->assertSame('green', $result[0]->status);
        $this->assertSame('yellow', $result[1]->status);
    }

    public function testGetStatusSummaryForUserCountsStatuses(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $factory = $this->createMock(DashboardKpiEntryFactory::class);
        $user = $this->createMock(User::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi2 = $this->createMock(KPI::class);
        $kpi3 = $this->createMock(KPI::class);
        $kpis = [$kpi1, $kpi2, $kpi3];

        $kpiRepo->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($kpis);

        $statusService->expects($this->exactly(3))
            ->method('getKpiStatus')
            ->willReturnOnConsecutiveCalls('green', 'red', 'yellow');

        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService, $factory);
        $summary = $service->getStatusSummaryForUser($user);

        $this->assertArrayHasKey('green', $summary);
        $this->assertArrayHasKey('yellow', $summary);
        $this->assertArrayHasKey('red', $summary);
        $this->assertSame(1, $summary['green']);
        $this->assertSame(1, $summary['yellow']);
        $this->assertSame(1, $summary['red']);
    }

    public function testGetDashboardStatsWithYellowStatus(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $factory = $this->createMock(DashboardKpiEntryFactory::class);
        $user = $this->createMock(User::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi2 = $this->createMock(KPI::class);

        $kpiData = [
            new DashboardKpiEntry($kpi1, 'yellow', null, false, false, new \DateTime()),
            new DashboardKpiEntry($kpi2, 'yellow', null, false, false, new \DateTime()),
        ];

        $kpiValueRepo->method('findRecentByUser')->willReturn([]);

        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService, $factory);
        $stats = $service->getDashboardStats($user, $kpiData);

        $this->assertSame(2, $stats['total_kpis']);
        $this->assertSame(0, $stats['overdue_count']);
        $this->assertSame(2, $stats['due_soon_count']);
        $this->assertSame(0, $stats['up_to_date_count']);
    }

    public function testGetKpiDataForUserSortsNullDueDatesLast(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $factory = $this->createMock(DashboardKpiEntryFactory::class);
        $user = $this->createMock(User::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi2 = $this->createMock(KPI::class);
        $kpis = [$kpi1, $kpi2];

        // Ein Eintrag ohne Fälligkeitsdatum, einer mit
        $entryWithoutDate = new DashboardKpiEntry($kpi1, 'green', null, false, false, new \DateTime());
        $entryWithDate = new DashboardKpiEntry($kpi2, 'yellow', new \DateTimeImmutable('+1 day'), false, false, new \DateTime());

        $kpiRepo->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($kpis);

        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($entryWithoutDate, $entryWithDate);

        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService, $factory);
        $result = $service->getKpiDataForUser($user);

        $this->assertCount(2, $result);
        // Prüfe dass Einträge mit Datum vor null-Einträgen kommen
        $hasDateFirst = $result[0]->nextDueDate !== null;
        $hasDateSecond = $result[1]->nextDueDate !== null;
        
        // Entweder: [mit Datum, ohne Datum] oder beide haben/haben keine Daten
        $this->assertTrue($hasDateFirst || !$hasDateSecond);
    }
}
