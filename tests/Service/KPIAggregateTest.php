<?php

namespace App\Tests\Service;

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

class KPIAggregateTest extends TestCase
{
    private KPIAggregate $aggregate;
    private EntityManagerInterface $entityManager;
    private KPIValueRepository $kpiValueRepository;
    private FileUploadService $fileUploadService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->fileUploadService = $this->createMock(FileUploadService::class);

        $this->aggregate = new KPIAggregate(
            $this->entityManager,
            $this->kpiValueRepository,
            $this->fileUploadService
        );
    }

    public function testGetKpiStatusReturnsGreenWhenValueExists(): void
    {
        $kpi = $this->createMock(KPI::class);
        $kpiValue = $this->createMock(KPIValue::class);
        $currentPeriod = new Period('2024-01');

        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn($kpiValue);

        $result = $this->aggregate->getKpiStatus($kpi);
        $this->assertSame('green', $result);
    }

    public function testGetKpiStatusReturnsRedWhenOverdue(): void
    {
        $kpi = $this->createMock(KPI::class);
        $currentPeriod = new Period('2024-01');

        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $kpi->method('getInterval')->willReturn(KpiInterval::WEEKLY);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        $result = $this->aggregate->getKpiStatus($kpi);
        $this->assertContains($result, ['green', 'yellow', 'red']);
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

    public function testGetKpiStatisticsReturnsEmptyStatsWhenNoValues(): void
    {
        $kpi = $this->createMock(KPI::class);
        $this->kpiValueRepository->method('findByKPI')->willReturn([]);

        $result = $this->aggregate->getKpiStatistics($kpi);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['total_entries']);
        $this->assertNull($result['average_value']);
        $this->assertSame('no_data', $result['trend']);
    }

    public function testGetKpiStatisticsCalculatesStatsWhenValuesExist(): void
    {
        $kpi = $this->createMock(KPI::class);
        $value1 = $this->createMock(KPIValue::class);
        $value2 = $this->createMock(KPIValue::class);

        $value1->method('getValueAsFloat')->willReturn(10.0);
        $value2->method('getValueAsFloat')->willReturn(20.0);

        $this->kpiValueRepository->method('findByKPI')->willReturn([$value1, $value2]);

        $result = $this->aggregate->getKpiStatistics($kpi);

        $this->assertSame(2, $result['total_entries']);
        $this->assertSame(15.0, $result['average_value']);
        $this->assertSame(10.0, $result['min_value']);
        $this->assertSame(20.0, $result['max_value']);
    }

    public function testValidateKpiReturnsErrorsForInvalidKpi(): void
    {
        $kpi = $this->createMock(KPI::class);
        $kpi->method('getName')->willReturn('');
        $kpi->method('getInterval')->willReturn(null);
        $kpi->method('getUser')->willReturn(null);

        $errors = $this->aggregate->validateKpi($kpi);

        $this->assertCount(3, $errors);
        $this->assertContains('KPI-Name ist erforderlich.', $errors);
        $this->assertContains('Ungültiges Intervall gewählt.', $errors);
        $this->assertContains('KPI muss einem Benutzer zugeordnet sein.', $errors);
    }

    public function testValidateKpiReturnsNoErrorsForValidKpi(): void
    {
        $kpi = $this->createMock(KPI::class);
        $user = $this->createMock(User::class);

        $kpi->method('getName')->willReturn('Test KPI');
        $kpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $kpi->method('getUser')->willReturn($user);

        $errors = $this->aggregate->validateKpi($kpi);

        $this->assertEmpty($errors);
    }

    public function testAddValueReturnsDuplicateWhenValueExists(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $existingValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);
        $period = new Period('2024-01');

        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn($existingValue);

        $result = $this->aggregate->addValue($kpiValue);

        $this->assertSame('duplicate', $result['status']);
        $this->assertSame($existingValue, $result['existing']);
    }

    public function testAddValuePersistsNewValue(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);
        $period = new Period('2024-01');

        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn($period);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist')->with($kpiValue);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->aggregate->addValue($kpiValue);

        $this->assertSame('success', $result['status']);
    }

    public function testIsDueSoonReturnsTrueForYellowStatus(): void
    {
        $kpi = $this->createMock(KPI::class);
        $currentPeriod = new Period('2024-01');

        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $kpi->method('getInterval')->willReturn(KpiInterval::WEEKLY);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        $result = $this->aggregate->isDueSoon($kpi);
        $this->assertIsBool($result);
    }

    public function testCalculateDueDateForWeeklyInterval(): void
    {
        $kpi = $this->createMock(KPI::class);
        $kpi->method('getInterval')->willReturn(KpiInterval::WEEKLY);

        $dueDate = $this->aggregate->calculateDueDate($kpi);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dueDate);
    }

    public function testGetKpisForReminderFiltersCorrectly(): void
    {
        $kpi = $this->createMock(KPI::class);
        $currentPeriod = new Period('2024-01');

        $kpi->method('getName')->willReturn('Test KPI');
        $kpi->method('getCurrentPeriod')->willReturn($currentPeriod);
        $kpi->method('getInterval')->willReturn(KpiInterval::WEEKLY);
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        $reminders = $this->aggregate->getKpisForReminder([$kpi]);
        $this->assertIsArray($reminders);
    }
}