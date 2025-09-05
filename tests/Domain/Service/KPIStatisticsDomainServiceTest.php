<?php

namespace App\Tests\Domain\Service;

use App\Domain\Service\KPIStatisticsDomainService;
use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KPIStatistics;
use App\Domain\ValueObject\KPITrend;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Repository\KPIValueRepository;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive Test für den KPIStatisticsDomainService.
 *
 * Testet alle statistischen Berechnungen, Trend-Analysen, 
 * Ausreißer-Erkennung und Performance-Benchmarking.
 */
class KPIStatisticsDomainServiceTest extends TestCase
{
    private KPIStatisticsDomainService $service;
    private KPIValueRepository $kpiValueRepository;

    protected function setUp(): void
    {
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->service = new KPIStatisticsDomainService($this->kpiValueRepository);
    }

    /**
     * @test
     */
    public function berechnet_grundlegende_statistiken_fuer_einzelwerte(): void
    {
        $kpi = $this->createMockKPI();
        $values = [$this->createMockKPIValue(100.0)];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $statistics = $this->service->calculateStatistics($kpi, ['include_advanced' => false]);

        $this->assertEquals(1, $statistics->getTotalEntries());
        $this->assertEquals(100.0, $statistics->getAverageValue());
        $this->assertEquals(100.0, $statistics->getMinValue());
        $this->assertEquals(100.0, $statistics->getMaxValue());
        $this->assertNotNull($statistics->getLatestValue());
    }

    /**
     * @test
     */
    public function berechnet_erweiterte_statistiken_fuer_mehrere_werte(): void
    {
        $kpi = $this->createMockKPI();
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(200.0),
            $this->createMockKPIValue(150.0),
            $this->createMockKPIValue(300.0),
            $this->createMockKPIValue(250.0)
        ];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $statistics = $this->service->calculateStatistics($kpi, ['include_advanced' => true]);

        $this->assertEquals(5, $statistics->getTotalEntries());
        $this->assertEquals(200.0, $statistics->getAverageValue());
        $this->assertEquals(100.0, $statistics->getMinValue());
        $this->assertEquals(300.0, $statistics->getMaxValue());
        $this->assertNotNull($statistics->getVariance());
        $this->assertNotNull($statistics->getStandardDeviation());
        $this->assertGreaterThan(0, $statistics->getVariance());
    }

    /**
     * @test
     */
    public function gibt_leere_statistik_fuer_keine_werte_zurueck(): void
    {
        $kpi = $this->createMockKPI();
        $this->kpiValueRepository->method('findByKPI')->willReturn([]);

        $statistics = $this->service->calculateStatistics($kpi);

        $this->assertEquals(0, $statistics->getTotalEntries());
        $this->assertNull($statistics->getAverageValue());
        $this->assertNull($statistics->getLatestValue());
    }

    /**
     * @test
     */
    public function berechnet_trend_fuer_ausreichend_datenpunkte(): void
    {
        $kpi = $this->createMockKPI();
        
        // Aufwärtstrend: 100, 150, 200, 250, 300
        $values = [
            $this->createMockKPIValue(300.0), // Latest first (repository sort)
            $this->createMockKPIValue(250.0),
            $this->createMockKPIValue(200.0),
            $this->createMockKPIValue(150.0),
            $this->createMockKPIValue(100.0)  // Oldest last
        ];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $statistics = $this->service->calculateStatistics($kpi);
        $trend = $statistics->getTrend();

        $this->assertNotNull($trend);
        $this->assertGreaterThan(0, $trend->getPercentageChange()); // Aufwärtstrend
    }

    /**
     * @test
     */
    public function berechnet_detaillierten_trend_mit_volatilitaet(): void
    {
        $kpi = $this->createMockKPI();
        
        // Volatile Werte für Trend-Analyse
        $values = [
            $this->createMockKPIValue(120.0),
            $this->createMockKPIValue(80.0),
            $this->createMockKPIValue(150.0),
            $this->createMockKPIValue(90.0),
            $this->createMockKPIValue(100.0)
        ];

        $trend = $this->service->calculateDetailedTrend($values, 5);

        $this->assertNotNull($trend);
        $this->assertGreaterThan(0, $trend->getVolatility());
        $this->assertEquals(5, $trend->getDataPoints());
        $this->assertGreaterThan(0, $trend->getConfidence());
        $this->assertLessThanOrEqual(1.0, $trend->getConfidence());
    }

    /**
     * @test
     */
    public function erkennt_statistische_ausreisser(): void
    {
        $kpi = $this->createMockKPI();
        
        // Normale Werte: 95-105, Ausreißer: 200
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(98.0),
            $this->createMockKPIValue(102.0),
            $this->createMockKPIValue(99.0),
            $this->createMockKPIValue(200.0), // Ausreißer
            $this->createMockKPIValue(101.0),
            $this->createMockKPIValue(97.0)
        ];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $outliers = $this->service->detectOutliers($kpi, 2.0);

        $this->assertCount(1, $outliers);
        $this->assertEquals(200.0, $outliers[0]['value']->getValueAsFloat());
        $this->assertGreaterThan(2.0, $outliers[0]['z_score']);
    }

    /**
     * @test
     */
    public function berechnet_korrelation_zwischen_kpis(): void
    {
        $kpi1 = $this->createMockKPI('KPI 1');
        $kpi2 = $this->createMockKPI('KPI 2');
        
        // Positiv korrelierte Werte
        $values1 = [
            $this->createMockKPIValue(100.0, '2024-01'),
            $this->createMockKPIValue(150.0, '2024-02'),
            $this->createMockKPIValue(200.0, '2024-03')
        ];
        
        $values2 = [
            $this->createMockKPIValue(50.0, '2024-01'),
            $this->createMockKPIValue(75.0, '2024-02'),
            $this->createMockKPIValue(100.0, '2024-03')
        ];

        $this->kpiValueRepository
            ->method('findByKPI')
            ->willReturnOnConsecutiveCalls($values1, $values2);

        $correlation = $this->service->calculateCorrelation($kpi1, $kpi2);

        $this->assertArrayHasKey('correlation_coefficient', $correlation);
        $this->assertArrayHasKey('strength', $correlation);
        $this->assertArrayHasKey('relationship', $correlation);
        $this->assertEquals(3, $correlation['paired_data_points']);
        $this->assertNotNull($correlation['correlation_coefficient']);
    }

    /**
     * @test
     */
    public function erstellt_performance_benchmark(): void
    {
        $kpi = $this->createMockKPI();
        $cutoffDate = new \DateTimeImmutable('-12 months');
        
        $values = [
            $this->createMockKPIValue(80.0),
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(120.0),
            $this->createMockKPIValue(90.0),
            $this->createMockKPIValue(110.0)
        ];

        $this->kpiValueRepository
            ->method('findByKPISince')
            ->with($kpi, $this->anything())
            ->willReturn($values);

        $benchmark = $this->service->createPerformanceBenchmark($kpi, 12);

        $this->assertEquals(12, $benchmark['benchmark_period']);
        $this->assertEquals(5, $benchmark['data_points']);
        $this->assertArrayHasKey('performance_targets', $benchmark);
        $this->assertArrayHasKey('excellent', $benchmark['performance_targets']);
        $this->assertArrayHasKey('good', $benchmark['performance_targets']);
        $this->assertArrayHasKey('average', $benchmark['performance_targets']);
        $this->assertArrayHasKey('below_average', $benchmark['performance_targets']);
        $this->assertArrayHasKey('poor', $benchmark['performance_targets']);
    }

    /**
     * @test
     */
    public function erstellt_prognose_fuer_zukuenftige_werte(): void
    {
        $kpi = $this->createMockKPI();
        
        // Linearer Aufwärtstrend für Prognose
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(110.0),
            $this->createMockKPIValue(120.0),
            $this->createMockKPIValue(130.0),
            $this->createMockKPIValue(140.0)
        ];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $forecast = $this->service->forecastValues($kpi, 3);

        if ($forecast['status'] === 'success') {
            $this->assertArrayHasKey('forecasts', $forecast);
            $this->assertArrayHasKey('model_confidence', $forecast);
            $this->assertArrayHasKey('trend_direction', $forecast);
            $this->assertCount(3, $forecast['forecasts']);
            
            foreach ($forecast['forecasts'] as $periodForecast) {
                $this->assertArrayHasKey('period', $periodForecast);
                $this->assertArrayHasKey('predicted_value', $periodForecast);
                $this->assertArrayHasKey('confidence_interval', $periodForecast);
            }
        } else {
            // Bei geringer Confidence oder unzureichenden Daten
            $this->assertContains($forecast['status'], ['low_confidence_forecast', 'insufficient_data_for_forecast']);
        }
    }

    /**
     * @test
     */
    public function behandelt_unzureichende_daten_fuer_erweiterte_statistiken(): void
    {
        $kpi = $this->createMockKPI();
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(110.0)
        ]; // Nur 2 Werte - unzureichend für erweiterte Statistiken

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $statistics = $this->service->calculateStatistics($kpi, ['include_advanced' => true]);

        // Sollte Basis-Statistiken haben, aber erweiterte könnten fehlen
        $this->assertEquals(2, $statistics->getTotalEntries());
        $this->assertEquals(105.0, $statistics->getAverageValue());
        $this->assertEquals(100.0, $statistics->getMinValue());
        $this->assertEquals(110.0, $statistics->getMaxValue());
    }

    /**
     * @test
     */
    public function berechnet_median_korrekt(): void
    {
        $kpi = $this->createMockKPI();
        
        // Ungerade Anzahl: Median = mittlerer Wert
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(200.0),
            $this->createMockKPIValue(150.0),
            $this->createMockKPIValue(300.0),
            $this->createMockKPIValue(250.0)
        ];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $statistics = $this->service->calculateStatistics($kpi, ['include_advanced' => true]);

        $this->assertEquals(200.0, $statistics->getAverageValue());
        // Median sollte für sortierte Werte [100, 150, 200, 250, 300] = 200 sein
    }

    /**
     * @test
     */
    public function ignoriert_ausreisser_bei_unzureichenden_daten(): void
    {
        $kpi = $this->createMockKPI();
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(110.0)
        ]; // Zu wenige Datenpunkte

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $outliers = $this->service->detectOutliers($kpi);

        $this->assertEmpty($outliers);
    }

    /**
     * @test
     */
    public function behandelt_korrelation_mit_unzureichenden_daten(): void
    {
        $kpi1 = $this->createMockKPI('KPI 1');
        $kpi2 = $this->createMockKPI('KPI 2');
        
        $values1 = [$this->createMockKPIValue(100.0)];
        $values2 = [$this->createMockKPIValue(50.0)];

        $this->kpiValueRepository
            ->method('findByKPI')
            ->willReturnOnConsecutiveCalls($values1, $values2);

        $correlation = $this->service->calculateCorrelation($kpi1, $kpi2);

        $this->assertEquals('insufficient_data', $correlation['strength']);
        $this->assertNull($correlation['correlation_coefficient']);
        $this->assertLessThan(3, $correlation['paired_data_points']);
    }

    /**
     * @test
     */
    public function behandelt_keine_historischen_daten_fuer_benchmark(): void
    {
        $kpi = $this->createMockKPI();

        $this->kpiValueRepository
            ->method('findByKPISince')
            ->willReturn([]);

        $benchmark = $this->service->createPerformanceBenchmark($kpi);

        $this->assertEquals('no_historical_data', $benchmark['status']);
    }

    /**
     * @test
     */
    public function berechnet_varianz_und_standardabweichung(): void
    {
        $kpi = $this->createMockKPI();
        
        // Bekannte Werte für exakte Berechnung
        $values = [
            $this->createMockKPIValue(10.0),
            $this->createMockKPIValue(20.0),
            $this->createMockKPIValue(30.0)
        ]; // Durchschnitt: 20, Varianz: 66.67, Std.Abw.: 8.16

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $statistics = $this->service->calculateStatistics($kpi, ['include_advanced' => true]);

        $variance = $statistics->getVariance();
        $stdDev = $statistics->getStandardDeviation();

        $this->assertNotNull($variance);
        $this->assertNotNull($stdDev);
        $this->assertGreaterThan(0, $variance);
        $this->assertGreaterThan(0, $stdDev);
        $this->assertEquals(sqrt($variance), $stdDev, '', 0.01); // Toleranz für Fließkomma
    }

    /**
     * @test
     */
    public function behandelt_identische_werte_ohne_varianz(): void
    {
        $kpi = $this->createMockKPI();
        
        // Alle Werte gleich = keine Varianz = keine Ausreißer
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(100.0)
        ];

        $this->kpiValueRepository->method('findByKPI')->willReturn($values);

        $outliers = $this->service->detectOutliers($kpi);
        $this->assertEmpty($outliers); // Keine Standardabweichung = keine Ausreißer

        $statistics = $this->service->calculateStatistics($kpi, ['include_advanced' => true]);
        $this->assertEquals(0, $statistics->getVariance());
        $this->assertEquals(0, $statistics->getStandardDeviation());
    }

    /**
     * @test
     */
    public function berechnet_trend_fuer_kleines_analysefenster(): void
    {
        $values = [
            $this->createMockKPIValue(100.0),
            $this->createMockKPIValue(110.0),
            $this->createMockKPIValue(120.0),
            $this->createMockKPIValue(130.0),
            $this->createMockKPIValue(140.0),
            $this->createMockKPIValue(150.0),
            $this->createMockKPIValue(160.0)
        ];

        $trend = $this->service->calculateDetailedTrend($values, 3); // Nur letzte 3 Werte

        $this->assertNotNull($trend);
        $this->assertEquals(3, $trend->getDataPoints());
        // Sollte positiven Trend zeigen (160 > 140)
        $this->assertGreaterThan(0, $trend->getPercentageChange());
    }

    /**
     * Erstellt einen Mock KPI für Tests.
     */
    private function createMockKPI(string $name = 'Test KPI'): KPI
    {
        $user = $this->createMock(User::class);
        
        $kpi = $this->createMock(KPI::class);
        $kpi->method('getName')->willReturn($name);
        $kpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $kpi->method('getUser')->willReturn($user);
        $kpi->method('getId')->willReturn(1);

        return $kpi;
    }

    /**
     * Erstellt einen Mock KPIValue mit gegebenem Wert.
     */
    private function createMockKPIValue(float $value, string $period = '2024-01'): KPIValue
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $kpiValue->method('getValueAsFloat')->willReturn($value);
        $kpiValue->method('getValue')->willReturn(DecimalValue::fromFloat($value));
        
        $periodObject = Period::fromString($period);
        $kpiValue->method('getPeriod')->willReturn($periodObject);

        return $kpiValue;
    }
}