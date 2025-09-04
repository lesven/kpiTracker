<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Service\KPIApplicationService;
use App\Service\KPIStatusService;
use PHPUnit\Framework\TestCase;

class KPIStatusServiceTest extends TestCase
{
    public function testGetKpiStatusDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $applicationService = $this->createMock(KPIApplicationService::class);
        $applicationService->expects($this->once())
            ->method('getKpiStatus')
            ->with($kpi)
            ->willReturn('green');

        $service = new KPIStatusService($applicationService);
        $result = $service->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testIsDueSoonDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $applicationService = $this->createMock(KPIApplicationService::class);
        $applicationService->expects($this->once())
            ->method('isDueSoon')
            ->with($kpi)
            ->willReturn(true);

        $service = new KPIStatusService($applicationService);
        $result = $service->isDueSoon($kpi);
        $this->assertTrue($result);
    }

    public function testIsOverdueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $applicationService = $this->createMock(KPIApplicationService::class);
        $applicationService->expects($this->once())
            ->method('isOverdue')
            ->with($kpi)
            ->willReturn(false);

        $service = new KPIStatusService($applicationService);
        $result = $service->isOverdue($kpi);
        $this->assertFalse($result);
    }

    public function testCalculateDueDateDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $applicationService = $this->createMock(KPIApplicationService::class);
        $expectedDate = new \DateTimeImmutable();
        $applicationService->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($expectedDate);

        $service = new KPIStatusService($applicationService);
        $result = $service->calculateDueDate($kpi);
        $this->assertSame($expectedDate, $result);
    }

    public function testGetDaysOverdueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $applicationService = $this->createMock(KPIApplicationService::class);
        $applicationService->expects($this->once())
            ->method('getDaysOverdue')
            ->with($kpi)
            ->willReturn(5);

        $service = new KPIStatusService($applicationService);
        $result = $service->getDaysOverdue($kpi);
        $this->assertSame(5, $result);
    }

    public function testGetKpisForReminderDelegatesToAggregate(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $applicationService = $this->createMock(KPIApplicationService::class);
        $expectedReminders = ['reminder1'];
        $applicationService->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 3, 0)
            ->willReturn($expectedReminders);

        $service = new KPIStatusService($applicationService);
        $result = $service->getKpisForReminder($kpis);
        $this->assertSame($expectedReminders, $result);
    }
}
