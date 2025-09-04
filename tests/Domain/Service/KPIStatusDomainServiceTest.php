<?php

namespace App\Tests\Domain\Service;

use App\Domain\Service\KPIStatusDomainService;
use App\Domain\ValueObject\KPIStatus;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use PHPUnit\Framework\TestCase;

/**
 * Test für den KPIStatusDomainService.
 *
 * Testet alle Status-Berechnungen, Fälligkeits-Logik und
 * komplexe Aggregations-Funktionen.
 */
class KPIStatusDomainServiceTest extends TestCase
{
    private KPIStatusDomainService $service;
    private KPIValueRepository $kpiValueRepository;

    protected function setUp(): void
    {
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->service = new KPIStatusDomainService($this->kpiValueRepository);
    }

    /**
     * @test
     */
    public function berechnet_gruenen_status_wenn_wert_vorhanden(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $existingValue = $this->createMock(KPIValue::class);

        $this->kpiValueRepository
            ->expects($this->once())
            ->method('findByKpiAndPeriod')
            ->willReturn($existingValue);

        $status = $this->service->calculateStatus($kpi);

        $this->assertTrue($status->isGreen());
    }

    /**
     * @test
     */
    public function berechnet_roten_status_wenn_ueberfaellig(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('-5 days')); // 5 Tage überfällig

        $this->kpiValueRepository
            ->expects($this->once())
            ->method('findByKpiAndPeriod')
            ->willReturn(null); // Kein Wert vorhanden

        $status = $this->service->calculateStatus($kpi);

        $this->assertTrue($status->isRed());
    }

    /**
     * @test
     */
    public function berechnet_gelben_status_wenn_bald_faellig(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('+2 days')); // In 2 Tagen fällig

        $this->kpiValueRepository
            ->expects($this->once())
            ->method('findByKpiAndPeriod')
            ->willReturn(null); // Kein Wert vorhanden

        $status = $this->service->calculateStatus($kpi);

        $this->assertTrue($status->isYellow());
    }

    /**
     * @test
     */
    public function berechnet_gruenen_status_wenn_noch_zeit(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('+7 days')); // Noch 7 Tage Zeit

        $this->kpiValueRepository
            ->expects($this->once())
            ->method('findByKpiAndPeriod')
            ->willReturn(null); // Kein Wert vorhanden

        $status = $this->service->calculateStatus($kpi);

        $this->assertTrue($status->isGreen());
    }

    /**
     * @test
     */
    public function kann_faelligkeitsdatum_fuer_woechentliches_intervall_berechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        
        $dueDate = $this->service->calculateDueDate($kpi);
        
        // Sollte der nächste Montag sein
        $this->assertEquals(1, $dueDate->format('N')); // ISO-8601: 1 = Montag
        $this->assertGreaterThan(new \DateTimeImmutable(), $dueDate);
    }

    /**
     * @test
     */
    public function kann_faelligkeitsdatum_fuer_monatliches_intervall_berechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::MONTHLY);
        
        $dueDate = $this->service->calculateDueDate($kpi);
        
        // Sollte der erste Tag des nächsten Monats sein
        $this->assertEquals('01', $dueDate->format('d'));
        $this->assertGreaterThan(new \DateTimeImmutable(), $dueDate);
    }

    /**
     * @test
     */
    public function kann_faelligkeitsdatum_fuer_quartalsmaessiges_intervall_berechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::QUARTERLY);
        
        $dueDate = $this->service->calculateDueDate($kpi);
        
        // Sollte der erste Tag eines Quartals sein (Jan, Apr, Jul, Okt)
        $month = (int) $dueDate->format('n');
        $this->assertContains($month, [1, 4, 7, 10]);
        $this->assertEquals('01', $dueDate->format('d'));
    }

    /**
     * @test
     */
    public function kann_tage_ueberfaellig_berechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('-3 days'));

        $daysOverdue = $this->service->getDaysOverdue($kpi);

        $this->assertEquals(3, $daysOverdue); // Positiv = überfällig
    }

    /**
     * @test
     */
    public function kann_negative_tage_bis_faelligkeit_berechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('+5 days'));

        $daysOverdue = $this->service->getDaysOverdue($kpi);

        $this->assertTrue($daysOverdue < 0); // Negativ = noch nicht fällig
    }

    /**
     * @test
     */
    public function kann_status_fuer_mehrere_kpis_berechnen(): void
    {
        $kpi1 = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi2 = $this->createMockKPI(KpiInterval::MONTHLY);
        $kpi3 = $this->createMockKPI(KpiInterval::QUARTERLY);

        // Mock verschiedene Szenarien
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(KPIValue::class), // kpi1: hat Wert = grün
                null, // kpi2: kein Wert
                null  // kpi3: kein Wert
            );

        $kpi1->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+1 day'));
        $kpi2->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days')); // gelb
        $kpi3->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-1 day')); // rot

        $results = $this->service->calculateStatusForMultiple([$kpi1, $kpi2, $kpi3]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->isGreen());
        $this->assertTrue($results[1]->isYellow());
        $this->assertTrue($results[2]->isRed());
    }

    /**
     * @test
     */
    public function kann_aggregierten_status_berechnen(): void
    {
        $greenKPI = $this->createMockKPI(KpiInterval::WEEKLY);
        $yellowKPI = $this->createMockKPI(KpiInterval::MONTHLY);
        $redKPI = $this->createMockKPI(KpiInterval::QUARTERLY);

        // Separate Mock-Konfiguration für jeden KPI
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturnCallback(function($kpi, $period) use ($greenKPI, $yellowKPI, $redKPI) {
                if ($kpi === $greenKPI) {
                    return $this->createMock(KPIValue::class); // grün
                }
                return null; // gelb/rot
            });

        $greenKPI->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+7 days'));
        $yellowKPI->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days'));
        $redKPI->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-1 day'));

        // Mindestens ein rot = gesamt rot
        $aggregatedStatus = $this->service->calculateAggregatedStatus([$greenKPI, $yellowKPI, $redKPI]);
        $this->assertTrue($aggregatedStatus->isRed());

        // Kein rot aber gelb = gesamt gelb
        $aggregatedStatus = $this->service->calculateAggregatedStatus([$greenKPI, $yellowKPI]);
        $this->assertTrue($aggregatedStatus->isYellow());

        // Alle grün = gesamt grün
        $aggregatedStatus = $this->service->calculateAggregatedStatus([$greenKPI]);
        $this->assertTrue($aggregatedStatus->isGreen());
    }

    /**
     * @test
     */
    public function kann_eskalationslevel_berechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        
        // Level 1 bei gelbem Status - noch nicht überfällig
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days'));
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);
        
        $escalationLevel = $this->service->calculateEscalationLevel($kpi);
        $this->assertEquals(1, $escalationLevel);

        // Level 1 bei gelbem Status
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days'));
        
        $escalationLevel = $this->service->calculateEscalationLevel($kpi);
        $this->assertEquals(1, $escalationLevel);

        // Level 2 bei rotem Status (leicht überfällig) - mit klarer Mock Konfiguration
        $overdueKpi = $this->createMock(KPI::class);
        $overdueKpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $overdueKpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $overdueKpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-2 days'));
        
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null); // Kein Wert vorhanden
        
        $escalationLevel = $this->service->calculateEscalationLevel($overdueKpi);
        $this->assertEquals(2, $escalationLevel);

        // Level 3 bei stark überfällig
        $stronglyOverdueKpi = $this->createMock(KPI::class);
        $stronglyOverdueKpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $stronglyOverdueKpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $stronglyOverdueKpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-8 days'));
        
        $escalationLevel = $this->service->calculateEscalationLevel($stronglyOverdueKpi);
        $this->assertEquals(3, $escalationLevel);
    }

    /**
     * @test
     */
    public function kann_konfigurierbare_schwellwerte_verwenden(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+5 days'));
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        // Standard-Schwellwert (3 Tage) = grün
        $status = $this->service->calculateStatus($kpi);
        $this->assertTrue($status->isGreen());

        // Höherer Schwellwert (7 Tage) = gelb
        $status = $this->service->calculateStatus($kpi, ['warning_threshold_days' => 7]);
        $this->assertTrue($status->isYellow());
    }

    /**
     * @test
     */
    public function kann_status_prioritaet_berechnen(): void
    {
        $redKPI = $this->createMockKPI(KpiInterval::WEEKLY);
        $yellowKPI = $this->createMockKPI(KpiInterval::MONTHLY);
        $greenKPI = $this->createMockKPI(KpiInterval::QUARTERLY);

        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturnOnConsecutiveCalls(null, null, $this->createMock(KPIValue::class));

        $redKPI->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-1 day'));
        $yellowKPI->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days'));
        $greenKPI->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+7 days'));

        $priorities = $this->service->calculatePriorities([$redKPI, $yellowKPI, $greenKPI]);

        $this->assertEquals(3, $priorities[0]['priority']); // rot = höchste Priorität
        $this->assertEquals(2, $priorities[1]['priority']); // gelb = mittlere Priorität
        $this->assertEquals(1, $priorities[2]['priority']); // grün = niedrige Priorität
    }

    /**
     * Erstellt einen Mock KPI mit gegebenem Intervall.
     */
    private function createMockKPI(KpiInterval $interval): KPI
    {
        $kpi = $this->createMock(KPI::class);
        $kpi->method('getInterval')->willReturn($interval);
        $kpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-W01'));
        
        return $kpi;
    }
}