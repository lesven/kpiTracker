<?php

namespace App\Tests\Service;

use App\Domain\Service\KPIDuplicateDetectionDomainService;
use App\Domain\Service\KPIReminderDomainService;
use App\Domain\Service\KPIStatisticsDomainService;
use App\Domain\Service\KPIStatusDomainService;
use App\Domain\Service\KPITrendDomainService;
use App\Domain\Service\KPIValidationDomainService;
use App\Domain\ValueObject\KPIStatistics;
use App\Domain\ValueObject\KPIStatus;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Repository\KPIValueRepository;
use App\Service\FileUploadService;
use App\Service\KPIAggregate;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Test für den refactored KPIAggregate (now Application Service).
 *
 * Testet die Delegation an Domain Services und die Koordination
 * zwischen verschiedenen Domain Services.
 */
class KPIAggregateTest extends TestCase
{
    private KPIAggregate $aggregate;
    private EntityManagerInterface $entityManager;
    private KPIValueRepository $kpiValueRepository;
    private FileUploadService $fileUploadService;
    private KPIStatusDomainService $statusService;
    private KPIStatisticsDomainService $statisticsService;
    private KPITrendDomainService $trendService;
    private KPIValidationDomainService $validationService;
    private KPIDuplicateDetectionDomainService $duplicateDetectionService;
    private KPIReminderDomainService $reminderService;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->statusService = $this->createMock(KPIStatusDomainService::class);
        $this->statisticsService = $this->createMock(KPIStatisticsDomainService::class);
        $this->trendService = $this->createMock(KPITrendDomainService::class);
        $this->validationService = $this->createMock(KPIValidationDomainService::class);
        $this->duplicateDetectionService = $this->createMock(KPIDuplicateDetectionDomainService::class);
        $this->reminderService = $this->createMock(KPIReminderDomainService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->aggregate = new KPIAggregate(
            $this->entityManager,
            $this->kpiValueRepository,
            $this->fileUploadService,
            $this->statusService,
            $this->statisticsService,
            $this->trendService,
            $this->validationService,
            $this->duplicateDetectionService,
            $this->reminderService,
            $this->eventDispatcher
        );
    }

    public function testGetKpiStatusDelegatesToStatusService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $greenStatus = KPIStatus::green();
        
        $this->statusService
            ->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($greenStatus);

        $result = $this->aggregate->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testGetKpiStatusValueObjectReturnsStatusValueObject(): void
    {
        $kpi = $this->createMock(KPI::class);
        $greenStatus = KPIStatus::green();
        
        $this->statusService
            ->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($greenStatus);

        $result = $this->aggregate->getKpiStatusValueObject($kpi);
        $this->assertInstanceOf(KPIStatus::class, $result);
        $this->assertTrue($result->isGreen());
    }

    public function testGetKpiStatusReturnsRedWhenOverdue(): void
    {
        $kpi = $this->createMock(KPI::class);
        $redStatus = KPIStatus::red();
        
        $this->statusService
            ->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($redStatus);

        $result = $this->aggregate->getKpiStatus($kpi);
        $this->assertSame('red', $result);
    }

    public function testHasCurrentValueReturnsTrueWhenValueExists(): void
    {
        $kpi = $this->createMock(KPI::class);
        $kpiValue = $this->createMock(KPIValue::class);
        $currentPeriod = new Period('2024-01');

        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn($kpiValue);

        $result = $this->aggregate->hasCurrentValue($kpi);
        $this->assertTrue($result);
    }

    public function testHasCurrentValueReturnsFalseWhenNoValueExists(): void
    {
        $kpi = $this->createMock(KPI::class);
        $currentPeriod = new Period('2024-01');

        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        $result = $this->aggregate->hasCurrentValue($kpi);
        $this->assertFalse($result);
    }

    public function testGetKpiStatisticsDelegatesToStatisticsService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $emptyStats = KPIStatistics::empty();
        
        $this->statisticsService
            ->expects($this->once())
            ->method('calculateStatistics')
            ->with($kpi)
            ->willReturn($emptyStats);

        $result = $this->aggregate->getKpiStatistics($kpi);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['total_entries']);
        $this->assertNull($result['average_value']);
        $this->assertTrue($result['trend'] === 'no_data');
    }

    public function testGetKpiStatisticsValueObjectReturnsValueObject(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statistics = KPIStatistics::empty();
        
        $this->statisticsService
            ->expects($this->once())
            ->method('calculateStatistics')
            ->with($kpi)
            ->willReturn($statistics);

        $result = $this->aggregate->getKpiStatisticsValueObject($kpi);
        
        $this->assertInstanceOf(KPIStatistics::class, $result);
    }

    public function testValidateKpiDelegatesToValidationService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedErrors = [
            'KPI-Name ist erforderlich.',
            'Ungültiges Intervall gewählt.',
            'KPI muss einem Benutzer zugeordnet sein.'
        ];
        
        $this->validationService
            ->expects($this->once())
            ->method('validateKpi')
            ->with($kpi)
            ->willReturn($expectedErrors);

        $errors = $this->aggregate->validateKpi($kpi);

        $this->assertSame($expectedErrors, $errors);
    }

    public function testValidateKpiWithContextDelegatesToValidationService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $context = ['allow_negative_targets' => true];
        $expectedErrors = [];
        
        $this->validationService
            ->expects($this->once())
            ->method('validateWithContext')
            ->with($kpi, $context)
            ->willReturn($expectedErrors);

        $errors = $this->aggregate->validateKpiWithContext($kpi, $context);

        $this->assertSame($expectedErrors, $errors);
    }

    public function testAddValueHandlesDuplicateDetection(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);
        $period = new Period('2024-01');
        $value = \App\Domain\ValueObject\DecimalValue::fromFloat(100.0);

        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $kpiValue->method('getValue')->willReturn($value);
        $kpiValue->method('getValueAsFloat')->willReturn(100.0);
        
        $this->validationService
            ->expects($this->once())
            ->method('checkForDuplicates')
            ->with($kpi, $period, $value)
            ->willReturn(true);

        $result = $this->aggregate->addValue($kpiValue);

        $this->assertSame('duplicate', $result['status']);
        $this->assertSame('Duplicate value detected', $result['message']);
    }

    public function testAddValueSuccessfullyPersistsValidValue(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);
        $period = new Period('2024-01');

        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $kpiValue->method('getValueAsFloat')->willReturn(100.0);
        $kpi->method('getRecordedEvents')->willReturn([]);
        
        // Mock Services
        $this->validationService
            ->method('checkForDuplicates')
            ->willReturn(false);
            
        $this->validationService
            ->method('validateKpiValue')
            ->willReturn([]);
            
        $this->statusService
            ->method('calculateStatus')
            ->willReturnOnConsecutiveCalls(
                KPIStatus::green(), // Previous status
                KPIStatus::green()  // Current status
            );
        
        // Expect persistence
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('persist')->with($kpiValue);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        $result = $this->aggregate->addValue($kpiValue);

        $this->assertSame('success', $result['status']);
        $this->assertSame($kpiValue, $result['kpi_value']);
        $this->assertArrayHasKey('events_dispatched', $result);
        $this->assertArrayHasKey('status_changed', $result);
    }

    public function testIsDueSoonDelegatesToStatusService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $yellowStatus = KPIStatus::yellow();
        
        $this->statusService
            ->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($yellowStatus);

        $result = $this->aggregate->isDueSoon($kpi);
        $this->assertTrue($result);
    }
    
    public function testIsOverdueDelegatesToStatusService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $redStatus = KPIStatus::red();
        
        $this->statusService
            ->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($redStatus);

        $result = $this->aggregate->isOverdue($kpi);
        $this->assertTrue($result);
    }

    public function testCalculateDueDateDelegatesToStatusService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedDueDate = new \DateTimeImmutable('+7 days');
        
        $this->statusService
            ->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($expectedDueDate);

        $dueDate = $this->aggregate->calculateDueDate($kpi);
        $this->assertSame($expectedDueDate, $dueDate);
    }
    
    public function testGetDaysOverdueDelegatesToStatusService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedDays = 5;
        
        $this->statusService
            ->expects($this->once())
            ->method('getDaysOverdue')
            ->with($kpi)
            ->willReturn($expectedDays);

        $days = $this->aggregate->getDaysOverdue($kpi);
        $this->assertSame($expectedDays, $days);
    }

    public function testGetKpisForReminderDelegatesToReminderService(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $expectedReminders = [
            [
                'kpi' => $kpis[0],
                'type' => 'upcoming',
                'days' => 3,
                'message' => 'Test reminder'
            ]
        ];
        
        $this->reminderService
            ->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 3, 0)
            ->willReturn($expectedReminders);

        $reminders = $this->aggregate->getKpisForReminder($kpis);
        $this->assertSame($expectedReminders, $reminders);
    }
    
    public function testCreateReminderMessageDelegatesToReminderService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedMessage = 'Your KPI is due in 3 days.';
        
        $this->reminderService
            ->expects($this->once())
            ->method('createPersonalizedMessage')
            ->with($kpi, 'upcoming', 3)
            ->willReturn($expectedMessage);

        $message = $this->aggregate->createReminderMessage($kpi, 'upcoming', 3);
        $this->assertSame($expectedMessage, $message);
    }
    
    public function testPerformBulkOperationDelegatesToCorrectService(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $context = ['test' => true];
        $expectedResults = ['result1', 'result2'];
        
        $this->validationService
            ->expects($this->once())
            ->method('validateMultipleKpis')
            ->with($kpis, $context)
            ->willReturn($expectedResults);

        $results = $this->aggregate->performBulkOperation($kpis, 'validate', $context);
        $this->assertSame($expectedResults, $results);
    }
    
    public function testPerformKpiAnalysisCoordinatesMultipleServices(): void
    {
        $kpi = $this->createMock(KPI::class);
        $values = [$this->createMock(KPIValue::class)];
        
        $this->kpiValueRepository
            ->expects($this->once())
            ->method('findByKPI')
            ->with($kpi)
            ->willReturn($values);
            
        $this->statusService->method('calculateStatus')->willReturn(KPIStatus::green());
        $this->statisticsService->method('calculateStatistics')->willReturn(KPIStatistics::empty());
        $this->trendService
            ->method('calculateTrend')
            ->willReturn(\App\Domain\ValueObject\KPITrend::stable());
        $this->validationService->method('validateKpi')->willReturn([]);
        $this->reminderService->method('shouldReceiveReminder')->willReturn(true);
        $this->duplicateDetectionService->method('identifyPatterns')->willReturn([]);

        $analysis = $this->aggregate->performKpiAnalysis($kpi);
        
        $this->assertArrayHasKey('kpi', $analysis);
        $this->assertArrayHasKey('status', $analysis);
        $this->assertArrayHasKey('statistics', $analysis);
        $this->assertArrayHasKey('trend', $analysis);
        $this->assertArrayHasKey('validation', $analysis);
        $this->assertArrayHasKey('reminder_info', $analysis);
        $this->assertArrayHasKey('duplicate_risks', $analysis);
        $this->assertArrayHasKey('analysis_timestamp', $analysis);
    }
}
