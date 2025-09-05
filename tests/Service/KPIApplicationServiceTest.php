<?php

namespace App\Tests\Service;

use App\Domain\Service\KPIDuplicateDetectionDomainService;
use App\Domain\Service\KPIReminderDomainService;
use App\Domain\Service\KPIStatisticsDomainService;
use App\Domain\Service\KPIStatusDomainService;
use App\Domain\Service\KPIValidationDomainService;
use App\Domain\ValueObject\KPIStatistics;
use App\Domain\ValueObject\KPIStatus;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Repository\KPIValueRepository;
use App\Service\FileUploadService;
use App\Service\KPIApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class KPIApplicationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private KPIValueRepository $kpiValueRepository;
    private FileUploadService $fileUploadService;
    private KPIStatusDomainService $statusService;
    private KPIStatisticsDomainService $statisticsService;
    private KPIValidationDomainService $validationService;
    private KPIDuplicateDetectionDomainService $duplicateDetectionService;
    private KPIReminderDomainService $reminderService;
    private EventDispatcherInterface $eventDispatcher;
    private KPIApplicationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->statusService = $this->createMock(KPIStatusDomainService::class);
        $this->statisticsService = $this->createMock(KPIStatisticsDomainService::class);
        $this->validationService = $this->createMock(KPIValidationDomainService::class);
        $this->duplicateDetectionService = $this->createMock(KPIDuplicateDetectionDomainService::class);
        $this->reminderService = $this->createMock(KPIReminderDomainService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->service = new KPIApplicationService(
            $this->entityManager,
            $this->kpiValueRepository,
            $this->fileUploadService,
            $this->statusService,
            $this->statisticsService,
            $this->validationService,
            $this->duplicateDetectionService,
            $this->reminderService,
            $this->eventDispatcher
        );
    }

    public function testGetKpiStatusReturnsStatusAsString(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statusVO = new KPIStatus('green');

        $this->statusService->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($statusVO);

        $result = $this->service->getKpiStatus($kpi);

        $this->assertSame('green', $result);
    }

    public function testGetKpiStatusValueObjectReturnsValueObject(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statusVO = new KPIStatus('green');

        $this->statusService->expects($this->once())
            ->method('calculateStatus')
            ->with($kpi)
            ->willReturn($statusVO);

        $result = $this->service->getKpiStatusValueObject($kpi);

        $this->assertSame($statusVO, $result);
    }

    public function testHasCurrentValueReturnsTrueWhenValueExists(): void
    {
        $kpi = $this->createMock(KPI::class);
        $period = new \App\Domain\ValueObject\Period('2024-01');
        $kpi->method('getCurrentPeriod')->willReturn($period);

        $kpiValue = $this->createMock(KPIValue::class);

        $this->kpiValueRepository->expects($this->once())
            ->method('findByKpiAndPeriod')
            ->with($kpi, $period)
            ->willReturn($kpiValue);

        $result = $this->service->hasCurrentValue($kpi);

        $this->assertTrue($result);
    }

    public function testHasCurrentValueReturnsFalseWhenNoValueExists(): void
    {
        $kpi = $this->createMock(KPI::class);
        $period = new \App\Domain\ValueObject\Period('2024-01');
        $kpi->method('getCurrentPeriod')->willReturn($period);

        $this->kpiValueRepository->expects($this->once())
            ->method('findByKpiAndPeriod')
            ->with($kpi, $period)
            ->willReturn(null);

        $result = $this->service->hasCurrentValue($kpi);

        $this->assertFalse($result);
    }

    public function testGetKpiStatisticsReturnsArray(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statisticsVO = new KPIStatistics(
            5,
            50.0,
            10.0,
            100.0,
            null,
            null,
            \App\Domain\ValueObject\KPITrend::stable()
        );

        $this->statisticsService->expects($this->once())
            ->method('calculateStatistics')
            ->with($kpi)
            ->willReturn($statisticsVO);

        $result = $this->service->getKpiStatistics($kpi);

        $this->assertIsArray($result);
    }

    public function testGetKpiStatisticsValueObjectReturnsValueObject(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statisticsVO = new KPIStatistics(
            5,
            50.0,
            10.0,
            100.0,
            null,
            null,
            \App\Domain\ValueObject\KPITrend::stable()
        );

        $this->statisticsService->expects($this->once())
            ->method('calculateStatistics')
            ->with($kpi)
            ->willReturn($statisticsVO);

        $result = $this->service->getKpiStatisticsValueObject($kpi);

        $this->assertSame($statisticsVO, $result);
    }

    public function testValidateKpiReturnsValidationErrors(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedErrors = ['name' => 'Name is required'];

        $this->validationService->expects($this->once())
            ->method('validateKpi')
            ->with($kpi)
            ->willReturn($expectedErrors);

        $result = $this->service->validateKpi($kpi);

        $this->assertSame($expectedErrors, $result);
    }

    public function testValidateKpiWithContextUsesContext(): void
    {
        $kpi = $this->createMock(KPI::class);
        $context = ['admin_mode' => true];
        $expectedErrors = [];

        $this->validationService->expects($this->once())
            ->method('validateWithContext')
            ->with($kpi, $context)
            ->willReturn($expectedErrors);

        $result = $this->service->validateKpiWithContext($kpi, $context);

        $this->assertSame($expectedErrors, $result);
    }

    public function testAddValueReturnsDuplicateWhenDuplicateDetected(): void
    {
        $kpi = $this->createMock(KPI::class);
        $kpiValue = $this->createMock(KPIValue::class);
        $period = new \App\Domain\ValueObject\Period('2024-01');
        
        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $kpiValue->method('getValueAsFloat')->willReturn(100.0);

        $duplicateResult = [
            'has_duplicate' => true,
            'existing_value' => $this->createMock(KPIValue::class),
            'similarity_score' => 0.95
        ];

        $this->duplicateDetectionService->expects($this->once())
            ->method('checkForDuplicate')
            ->with($kpiValue)
            ->willReturn($duplicateResult);

        $result = $this->service->addValue($kpiValue);

        $this->assertSame('duplicate', $result['status']);
        $this->assertArrayHasKey('existing', $result);
        $this->assertArrayHasKey('similarity', $result);
    }

    public function testAddValueReturnsValidationErrorWhenValidationFails(): void
    {
        $kpi = $this->createMock(KPI::class);
        $kpiValue = $this->createMock(KPIValue::class);
        $period = new \App\Domain\ValueObject\Period('2024-01');
        
        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $kpiValue->method('getValueAsFloat')->willReturn(100.0);

        $this->duplicateDetectionService->method('checkForDuplicate')
            ->willReturn(['has_duplicate' => false]);

        $validationErrors = ['value' => 'Invalid value'];
        $this->validationService->expects($this->once())
            ->method('validateKpiValue')
            ->with($kpiValue)
            ->willReturn($validationErrors);

        $result = $this->service->addValue($kpiValue);

        $this->assertSame('validation_error', $result['status']);
        $this->assertSame($validationErrors, $result['errors']);
    }

    public function testAddValueSuccessfullyAddsValue(): void
    {
        $user = $this->createMock(User::class);
        $kpi = $this->createMock(KPI::class);
        $period = new \App\Domain\ValueObject\Period('2024-01');
        
        $kpi->method('getUser')->willReturn($user);
        $kpi->method('getRecordedEvents')->willReturn([]);
        
        $kpiValue = $this->createMock(KPIValue::class);
        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $kpiValue->method('getValueAsFloat')->willReturn(100.0);

        $statusVO = new KPIStatus('green');

        $this->duplicateDetectionService->method('checkForDuplicate')
            ->willReturn(['has_duplicate' => false]);
        
        $this->validationService->method('validateKpiValue')
            ->willReturn([]);

        $this->statusService->method('calculateStatus')
            ->willReturn($statusVO);

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($kpiValue);
        $this->entityManager->expects($this->once())
            ->method('flush');
        $this->entityManager->expects($this->once())
            ->method('commit');

        $result = $this->service->addValue($kpiValue);

        $this->assertSame('success', $result['status']);
        $this->assertSame($kpiValue, $result['kpi_value']);
    }

    public function testIsDueSoonReturnsTrueForYellowStatus(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statusVO = new KPIStatus('yellow');

        $this->statusService->method('calculateStatus')
            ->with($kpi)
            ->willReturn($statusVO);

        $result = $this->service->isDueSoon($kpi);

        $this->assertTrue($result);
    }

    public function testIsOverdueReturnsTrueForRedStatus(): void
    {
        $kpi = $this->createMock(KPI::class);
        $statusVO = new KPIStatus('red');

        $this->statusService->method('calculateStatus')
            ->with($kpi)
            ->willReturn($statusVO);

        $result = $this->service->isOverdue($kpi);

        $this->assertTrue($result);
    }

    public function testCalculateDueDateReturnsDueDate(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedDate = new \DateTimeImmutable('2024-01-15');

        $this->statusService->expects($this->once())
            ->method('calculateDueDate')
            ->with($kpi)
            ->willReturn($expectedDate);

        $result = $this->service->calculateDueDate($kpi);

        $this->assertSame($expectedDate, $result);
    }

    public function testGetDaysOverdueReturnsDaysFromService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedDays = 5;

        $this->statusService->expects($this->once())
            ->method('getDaysOverdue')
            ->with($kpi)
            ->willReturn($expectedDays);

        $result = $this->service->getDaysOverdue($kpi);

        $this->assertSame($expectedDays, $result);
    }

    public function testGetKpisForReminderDelegatesToReminderService(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $expectedReminders = ['reminder1', 'reminder2'];

        $this->reminderService->expects($this->once())
            ->method('getKpisForReminder')
            ->with($kpis, 3, 0)
            ->willReturn($expectedReminders);

        $result = $this->service->getKpisForReminder($kpis);

        $this->assertSame($expectedReminders, $result);
    }

    public function testCreateReminderMessageDelegatesToReminderService(): void
    {
        $kpi = $this->createMock(KPI::class);
        $expectedMessage = 'Your KPI is due soon';

        $this->reminderService->expects($this->once())
            ->method('createPersonalizedMessage')
            ->with($kpi, 'upcoming', 3)
            ->willReturn($expectedMessage);

        $result = $this->service->createReminderMessage($kpi, 'upcoming', 3);

        $this->assertSame($expectedMessage, $result);
    }

    public function testPerformBulkOperationValidate(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $context = ['admin' => true];
        $expectedResult = ['validation_results'];

        $this->validationService->expects($this->once())
            ->method('validateMultipleKpis')
            ->with($kpis, $context)
            ->willReturn($expectedResult);

        $result = $this->service->performBulkOperation($kpis, 'validate', $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testPerformBulkOperationCalculateStatus(): void
    {
        $kpis = [$this->createMock(KPI::class)];
        $expectedResult = ['status_results'];

        $this->statusService->expects($this->once())
            ->method('calculateStatusForMultiple')
            ->with($kpis)
            ->willReturn($expectedResult);

        $result = $this->service->performBulkOperation($kpis, 'calculate_status');

        $this->assertSame($expectedResult, $result);
    }

    public function testPerformBulkOperationThrowsExceptionForUnknownOperation(): void
    {
        $kpis = [$this->createMock(KPI::class)];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown bulk operation: unknown');

        $this->service->performBulkOperation($kpis, 'unknown');
    }

    public function testPerformKpiAnalysisReturnsCompleteAnalysis(): void
    {
        $kpi = $this->createMock(KPI::class);
        $values = [$this->createMock(KPIValue::class)];
        $statusVO = new KPIStatus('green');
        $statisticsVO = new KPIStatistics(
            5,
            50.0,
            10.0,
            100.0,
            null,
            null,
            \App\Domain\ValueObject\KPITrend::stable()
        );

        $this->kpiValueRepository->method('findByKPI')
            ->with($kpi)
            ->willReturn($values);

        $this->statusService->method('calculateStatus')
            ->with($kpi)
            ->willReturn($statusVO);

        $this->statisticsService->method('calculateStatistics')
            ->with($kpi)
            ->willReturn($statisticsVO);

        $this->statisticsService->method('calculateDetailedTrend')
            ->with($values)
            ->willReturn(\App\Domain\ValueObject\KPITrend::stable());

        $this->validationService->method('validateKpi')
            ->with($kpi)
            ->willReturn([]);

        $this->reminderService->method('shouldReceiveReminder')
            ->with($kpi)
            ->willReturn(false);

        $this->duplicateDetectionService->method('identifyPatterns')
            ->with($kpi)
            ->willReturn([]);

        $result = $this->service->performKpiAnalysis($kpi);

        $this->assertArrayHasKey('kpi', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('trend', $result);
        $this->assertArrayHasKey('validation', $result);
        $this->assertArrayHasKey('reminder_info', $result);
        $this->assertArrayHasKey('duplicate_risks', $result);
        $this->assertArrayHasKey('analysis_timestamp', $result);
        $this->assertSame($kpi, $result['kpi']);
    }
}
