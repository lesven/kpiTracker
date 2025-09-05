<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Service\KPIApplicationService;
use App\Service\KPIStatusService;
use PHPUnit\Framework\TestCase;

class KPIStatusServiceTest extends TestCase
{
    private KPIApplicationService $applicationService;
    private KPIStatusService $service;

    protected function setUp(): void
    {
        $this->applicationService = $this->createMock(KPIApplicationService::class);
        $this->service = new KPIStatusService($this->applicationService);
    }

    public function testGetKpiStatusDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('getKpiStatus')
            ->with($kpi)
            ->willReturn('green');

        $result = $this->service->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testGetKpiStatusReturnsAllPossibleValues(): void
    {
        $kpi1 = $this->createMock(KPI::class);
        $kpi2 = $this->createMock(KPI::class);
        $kpi3 = $this->createMock(KPI::class);
        $kpi4 = $this->createMock(KPI::class);
        
        // Test verschiedene Status-Werte mit separaten Erwartungen
        $this->applicationService->expects($this->exactly(4))
            ->method('getKpiStatus')
            ->willReturnCallback(function($kpi) use ($kpi1, $kpi2, $kpi3, $kpi4) {
                if ($kpi === $kpi1) return 'green';
                if ($kpi === $kpi2) return 'yellow';
                if ($kpi === $kpi3) return 'red';
                if ($kpi === $kpi4) return 'gray';
                return 'unknown';
            });

        $this->assertSame('green', $this->service->getKpiStatus($kpi1));
        $this->assertSame('yellow', $this->service->getKpiStatus($kpi2));
        $this->assertSame('red', $this->service->getKpiStatus($kpi3));
        $this->assertSame('gray', $this->service->getKpiStatus($kpi4));
    }

    public function testCalculateDueDateWithPastDate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $pastDate = new \DateTimeImmutable('2023-01-01');
        
        $this->applicationService->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($pastDate);

        $result = $this->service->calculateDueDate($kpi);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals($pastDate, $result);
    }

    public function testIsDueSoonDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('isDueSoon')
            ->with($kpi)
            ->willReturn(true);

        $result = $this->service->isDueSoon($kpi);
        $this->assertTrue($result);
    }

    public function testIsDueSoonReturnsFalse(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('isDueSoon')
            ->with($kpi)
            ->willReturn(false);

        $result = $this->service->isDueSoon($kpi);
        $this->assertFalse($result);
    }

    public function testIsOverdueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('isOverdue')
            ->with($kpi)
            ->willReturn(false);

        $result = $this->service->isOverdue($kpi);
        $this->assertFalse($result);
    }

    public function testIsOverdueReturnsTrue(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('isOverdue')
            ->with($kpi)
            ->willReturn(true);

        $result = $this->service->isOverdue($kpi);
        $this->assertTrue($result);
    }

    public function testCalculateDueDateDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedDate = new \DateTimeImmutable();
        
        $this->applicationService->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($expectedDate);

        $result = $this->service->calculateDueDate($kpi);
        $this->assertSame($expectedDate, $result);
    }

    public function testCalculateDueDateWithRecentPastDate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $pastDate = new \DateTimeImmutable('-1 week');
        
        $this->applicationService->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($pastDate);

        $result = $this->service->calculateDueDate($kpi);
        $this->assertSame($pastDate, $result);
    }

    public function testGetDaysOverdueDelegatesToAggregate(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('getDaysOverdue')
            ->with($kpi)
            ->willReturn(5);

        $result = $this->service->getDaysOverdue($kpi);
        $this->assertSame(5, $result);
    }

    public function testGetDaysOverdueReturnsZero(): void
    {
        $kpi = $this->createMock(KPI::class);
        
        $this->applicationService->expects($this->once())
            ->method('getDaysOverdue')
            ->with($kpi)
            ->willReturn(0);

        $result = $this->service->getDaysOverdue($kpi);
        $this->assertSame(0, $result);
    }

    public function testGetKpisForReminderDelegatesToAggregate(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $expectedReminders = ['reminder1'];
        
        $this->applicationService->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 3, 0)
            ->willReturn($expectedReminders);

        $result = $this->service->getKpisForReminder($kpis);
        $this->assertSame($expectedReminders, $result);
    }

    public function testGetKpisForReminderWithCustomParameters(): void
    {
        $kpis = [
            $this->createMock(KPI::class), 
            $this->createMock(KPI::class)
        ];
        $expectedReminders = ['reminder1', 'reminder2'];
        
        $this->applicationService->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 5, 1)
            ->willReturn($expectedReminders);

        $result = $this->service->getKpisForReminder($kpis, 5, 1);
        $this->assertSame($expectedReminders, $result);
    }

    public function testGetKpisForReminderWithEmptyArray(): void
    {
        $kpis = [];
        $expectedReminders = [];
        
        $this->applicationService->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 3, 0)
            ->willReturn($expectedReminders);

        $result = $this->service->getKpisForReminder($kpis);
        $this->assertSame($expectedReminders, $result);
    }
}
