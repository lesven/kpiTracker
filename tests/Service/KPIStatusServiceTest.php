<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Service\KPIAggregate;
use App\Service\KPIStatusService;
use PHPUnit\Framework\TestCase;

class KPIStatusServiceTest extends TestCase
{
    public function testGetKpiStatusDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $aggregate->expects($this->once())
            ->method('getKpiStatus')
            ->with($kpi)
            ->willReturn('green');

        $service = new KPIStatusService($aggregate);
        $result = $service->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testIsDueSoonDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $aggregate->expects($this->once())
            ->method('isDueSoon')
            ->with($kpi)
            ->willReturn(true);

        $service = new KPIStatusService($aggregate);
        $result = $service->isDueSoon($kpi);
        $this->assertTrue($result);
    }

    public function testIsOverdueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $aggregate->expects($this->once())
            ->method('isOverdue')
            ->with($kpi)
            ->willReturn(false);

        $service = new KPIStatusService($aggregate);
        $result = $service->isOverdue($kpi);
        $this->assertFalse($result);
    }

    public function testCalculateDueDateDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedDate = new \DateTimeImmutable();
        $aggregate->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($expectedDate);

        $service = new KPIStatusService($aggregate);
        $result = $service->calculateDueDate($kpi);
        $this->assertSame($expectedDate, $result);
    }

    public function testGetDaysOverdueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $aggregate->expects($this->once())
            ->method('getDaysOverdue')
            ->with($kpi)
            ->willReturn(5);

        $service = new KPIStatusService($aggregate);
        $result = $service->getDaysOverdue($kpi);
        $this->assertSame(5, $result);
    }

    public function testGetKpisForReminderDelegatesToAggregate(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedReminders = ['reminder1'];
        $aggregate->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 3, 0)
            ->willReturn($expectedReminders);

        $service = new KPIStatusService($aggregate);
        $result = $service->getKpisForReminder($kpis);
        $this->assertSame($expectedReminders, $result);
    }
}
