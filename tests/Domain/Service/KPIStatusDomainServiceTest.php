<?php

namespace App\Tests\Domain\Service;

use App\Domain\Service\KPIStatusDomainService;
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
    public function berechnetGruenenStatusWennWertVorhanden(): void
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
    public function berechnetRotenStatusWennUeberfaellig(): void
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
    public function berechnetGelbenStatusWennBaldFaellig(): void
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
    public function berechnetGruenenStatusWennNochZeit(): void
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
    public function kannFaelligkeitsdatumFuerWoechentlichesIntervallBerechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);

        $dueDate = $this->service->calculateDueDate($kpi);

        // Sollte der nächste Montag sein
        $this->assertSame(1, $dueDate->format('N')); // ISO-8601: 1 = Montag
        $this->assertGreaterThan(new \DateTimeImmutable(), $dueDate);
    }

    /**
     * @test
     */
    public function kannFaelligkeitsdatumFuerMonatlichesIntervallBerechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::MONTHLY);

        $dueDate = $this->service->calculateDueDate($kpi);

        // Sollte der erste Tag des nächsten Monats sein
        $this->assertSame('01', $dueDate->format('d'));
        $this->assertGreaterThan(new \DateTimeImmutable(), $dueDate);
    }

    /**
     * @test
     */
    public function kannFaelligkeitsdatumFuerQuartalsmaessigesIntervallBerechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::QUARTERLY);

        $dueDate = $this->service->calculateDueDate($kpi);

        // Sollte der erste Tag eines Quartals sein (Jan, Apr, Jul, Okt)
        $month = (int) $dueDate->format('n');
        $this->assertContains($month, [1, 4, 7, 10]);
        $this->assertSame('01', $dueDate->format('d'));
    }

    /**
     * @test
     */
    public function kannTageUeberfaelligBerechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('-3 days'));

        $daysOverdue = $this->service->getDaysOverdue($kpi);

        $this->assertSame(3, $daysOverdue); // Positiv = überfällig
    }

    /**
     * @test
     */
    public function kannNegativeTageBisFaelligkeitBerechnen(): void
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
    public function kannStatusFuerMehrereKpisBerechnen(): void
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
    public function kannAggregiertenStatusBerechnen(): void
    {
        $greenKPI = $this->createMockKPI(KpiInterval::WEEKLY);
        $yellowKPI = $this->createMockKPI(KpiInterval::MONTHLY);
        $redKPI = $this->createMockKPI(KpiInterval::QUARTERLY);

        // Separate Mock-Konfiguration für jeden KPI
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturnCallback(function ($kpi, $period) use ($greenKPI) {
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
    public function kannEskalationslevelBerechnen(): void
    {
        $kpi = $this->createMockKPI(KpiInterval::WEEKLY);

        // Level 1 bei gelbem Status - noch nicht überfällig
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days'));
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);

        $escalationLevel = $this->service->calculateEscalationLevel($kpi);
        $this->assertSame(1, $escalationLevel);

        // Level 1 bei gelbem Status
        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null);
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+2 days'));

        $escalationLevel = $this->service->calculateEscalationLevel($kpi);
        $this->assertSame(1, $escalationLevel);

        // Level 2 bei rotem Status (leicht überfällig) - mit klarer Mock Konfiguration
        $overdueKpi = $this->createMock(KPI::class);
        $overdueKpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $overdueKpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $overdueKpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-2 days'));

        $this->kpiValueRepository->method('findByKpiAndPeriod')->willReturn(null); // Kein Wert vorhanden

        $escalationLevel = $this->service->calculateEscalationLevel($overdueKpi);
        $this->assertSame(2, $escalationLevel);

        // Level 3 bei stark überfällig
        $stronglyOverdueKpi = $this->createMock(KPI::class);
        $stronglyOverdueKpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $stronglyOverdueKpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $stronglyOverdueKpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-8 days'));

        $escalationLevel = $this->service->calculateEscalationLevel($stronglyOverdueKpi);
        $this->assertSame(3, $escalationLevel);
    }

    /**
     * @test
     */
    public function kannKonfigurierbareSchwellwerteVerwenden(): void
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
    public function kannStatusPrioritaetBerechnen(): void
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

        $this->assertSame(3, $priorities[0]['priority']); // rot = höchste Priorität
        $this->assertSame(2, $priorities[1]['priority']); // gelb = mittlere Priorität
        $this->assertSame(1, $priorities[2]['priority']); // grün = niedrige Priorität
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
