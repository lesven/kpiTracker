<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\KPIStatistics;
use App\Domain\ValueObject\KPITrend;
use App\Entity\KPIValue;
use PHPUnit\Framework\TestCase;

/**
 * Test für das KPIStatistics Value Object.
 *
 * Testet alle statistischen Berechnungen, Edge Cases und die
 * Immutabilität des Value Objects.
 */
class KPIStatisticsTest extends TestCase
{
    /**
     * @test
     */
    public function kann_leere_statistik_erstellen(): void
    {
        $stats = KPIStatistics::empty();

        $this->assertEquals(0, $stats->getTotalEntries());
        $this->assertNull($stats->getAverageValue());
        $this->assertNull($stats->getMinValue());
        $this->assertNull($stats->getMaxValue());
        $this->assertNull($stats->getLatestValue());
        $this->assertTrue($stats->getTrend()->isNoData());
        $this->assertFalse($stats->hasData());
        $this->assertTrue($stats->isEmpty());
    }

    /**
     * @test
     */
    public function kann_statistik_mit_einzelwert_erstellen(): void
    {
        $kpiValue = $this->createMockKPIValue(100.0);
        $trend = KPITrend::noData();

        $stats = KPIStatistics::fromData(
            totalEntries: 1,
            averageValue: 100.0,
            minValue: 100.0,
            maxValue: 100.0,
            latestValue: $kpiValue,
            trend: $trend
        );

        $this->assertEquals(1, $stats->getTotalEntries());
        $this->assertEquals(100.0, $stats->getAverageValue());
        $this->assertEquals(100.0, $stats->getMinValue());
        $this->assertEquals(100.0, $stats->getMaxValue());
        $this->assertEquals($kpiValue, $stats->getLatestValue());
        $this->assertTrue($stats->hasData());
        $this->assertFalse($stats->isEmpty());
    }

    /**
     * @test
     */
    public function kann_statistik_mit_mehreren_werten_erstellen(): void
    {
        $latestValue = $this->createMockKPIValue(150.0);
        $trend = KPITrend::rising();

        $stats = KPIStatistics::fromData(
            totalEntries: 5,
            averageValue: 120.0,
            minValue: 50.0,
            maxValue: 200.0,
            latestValue: $latestValue,
            trend: $trend
        );

        $this->assertEquals(5, $stats->getTotalEntries());
        $this->assertEquals(120.0, $stats->getAverageValue());
        $this->assertEquals(50.0, $stats->getMinValue());
        $this->assertEquals(200.0, $stats->getMaxValue());
        $this->assertEquals($latestValue, $stats->getLatestValue());
        $this->assertTrue($stats->getTrend()->isRising());
    }

    /**
     * @test
     */
    public function kann_erweiterte_statistiken_berechnen(): void
    {
        $stats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 100.0,
            minValue: 80.0,
            maxValue: 120.0,
            latestValue: $this->createMockKPIValue(110.0),
            trend: KPITrend::stable()
        );

        // Range-Berechnung
        $this->assertEquals(40.0, $stats->getRange());

        // Coefficient of Variation (20% Standardabweichung angenommen)
        $this->assertEquals(20.0, $stats->getCoefficientOfVariation());

        // Stability Rating basierend auf CV
        $this->assertEquals('moderate', $stats->getStabilityRating());
    }

    /**
     * @test
     */
    public function kann_stability_rating_korrekt_berechnen(): void
    {
        // Sehr stabil (CV < 10%)
        $stableStats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 100.0,
            minValue: 95.0,
            maxValue: 105.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::stable()
        );
        $this->assertEquals('high', $stableStats->getStabilityRating());

        // Mäßig stabil (10% <= CV < 25%)
        $moderateStats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 100.0,
            minValue: 80.0,
            maxValue: 120.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::stable()
        );
        $this->assertEquals('moderate', $moderateStats->getStabilityRating());

        // Instabil (CV >= 25%)
        $unstableStats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 100.0,
            minValue: 50.0,
            maxValue: 150.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::volatile()
        );
        $this->assertEquals('low', $unstableStats->getStabilityRating());
    }

    /**
     * @test
     */
    public function kann_performance_gegen_zielwert_bewerten(): void
    {
        $stats = KPIStatistics::fromData(
            totalEntries: 5,
            averageValue: 120.0,
            minValue: 100.0,
            maxValue: 140.0,
            latestValue: $this->createMockKPIValue(130.0),
            trend: KPITrend::rising()
        );

        // Über Zielwert
        $this->assertEquals(20.0, $stats->getPerformanceVsTarget(100.0));
        $this->assertEquals('above_target', $stats->getPerformanceRating(100.0));

        // Genau Zielwert
        $this->assertEquals(0.0, $stats->getPerformanceVsTarget(120.0));
        $this->assertEquals('on_target', $stats->getPerformanceRating(120.0));

        // Unter Zielwert
        $this->assertEquals(-16.67, $stats->getPerformanceVsTarget(150.0));
        $this->assertEquals('below_target', $stats->getPerformanceRating(150.0));
    }

    /**
     * @test
     */
    public function kann_array_darstellung_ausgeben(): void
    {
        $kpiValue = $this->createMockKPIValue(110.0);
        $stats = KPIStatistics::fromData(
            totalEntries: 5,
            averageValue: 100.0,
            minValue: 80.0,
            maxValue: 120.0,
            latestValue: $kpiValue,
            trend: KPITrend::rising()
        );

        $array = $stats->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(5, $array['total_entries']);
        $this->assertEquals(100.0, $array['average_value']);
        $this->assertEquals(80.0, $array['min_value']);
        $this->assertEquals(120.0, $array['max_value']);
        $this->assertEquals($kpiValue, $array['latest_value']);
        $this->assertEquals('rising', $array['trend']);
        $this->assertEquals(40.0, $array['range']);
        $this->assertEquals(20.0, $array['coefficient_of_variation']);
        $this->assertEquals('moderate', $array['stability_rating']);
    }

    /**
     * @test
     */
    public function kann_json_serialisierung_handhaben(): void
    {
        $stats = KPIStatistics::fromData(
            totalEntries: 3,
            averageValue: 100.0,
            minValue: 90.0,
            maxValue: 110.0,
            latestValue: $this->createMockKPIValue(105.0),
            trend: KPITrend::stable()
        );

        $json = json_encode($stats);
        $this->assertIsString($json);
        
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(3, $decoded['total_entries']);
        $this->assertEquals(100.0, $decoded['average_value']);
    }

    /**
     * @test
     */
    public function kann_zusammenfassung_generieren(): void
    {
        $stats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 95.5,
            minValue: 80.0,
            maxValue: 110.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::rising()
        );

        $summary = $stats->getSummary();

        $this->assertIsString($summary);
        $this->assertStringContainsString('10 Werte', $summary);
        $this->assertStringContainsString('Durchschnitt: 95.5', $summary);
        $this->assertStringContainsString('steigend', $summary);
    }

    /**
     * @test
     */
    public function kann_outlier_erkennen(): void
    {
        $stats = KPIStatistics::fromData(
            totalEntries: 100,
            averageValue: 100.0,
            minValue: 10.0,  // Potentieller Outlier
            maxValue: 200.0, // Potentieller Outlier
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::stable()
        );

        $this->assertTrue($stats->hasOutliers());
    }

    /**
     * @test
     */
    public function kann_qualitaetsbewertung_erstellen(): void
    {
        // Hohe Qualität: Viele Werte, stabil, keine Outlier
        $highQuality = KPIStatistics::fromData(
            totalEntries: 50,
            averageValue: 100.0,
            minValue: 95.0,
            maxValue: 105.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::stable()
        );

        $qualityScore = $highQuality->getDataQualityScore();
        $this->assertGreaterThan(80, $qualityScore);
        $this->assertEquals('high', $highQuality->getDataQualityRating());

        // Niedrige Qualität: Wenige Werte, instabil
        $lowQuality = KPIStatistics::fromData(
            totalEntries: 2,
            averageValue: 100.0,
            minValue: 50.0,
            maxValue: 150.0,
            latestValue: $this->createMockKPIValue(75.0),
            trend: KPITrend::volatile()
        );

        $qualityScore = $lowQuality->getDataQualityScore();
        $this->assertLessThan(50, $qualityScore);
        $this->assertEquals('low', $lowQuality->getDataQualityRating());
    }

    /**
     * @test
     */
    public function value_object_ist_immutable(): void
    {
        $originalStats = KPIStatistics::fromData(
            totalEntries: 5,
            averageValue: 100.0,
            minValue: 80.0,
            maxValue: 120.0,
            latestValue: $this->createMockKPIValue(110.0),
            trend: KPITrend::stable()
        );

        // "Änderung" durch Erstellen neuer Instanz
        $newStats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 200.0,
            minValue: 180.0,
            maxValue: 220.0,
            latestValue: $this->createMockKPIValue(210.0),
            trend: KPITrend::rising()
        );

        // Original sollte unverändert sein
        $this->assertEquals(5, $originalStats->getTotalEntries());
        $this->assertEquals(100.0, $originalStats->getAverageValue());
        $this->assertEquals(10, $newStats->getTotalEntries());
        $this->assertEquals(200.0, $newStats->getAverageValue());
    }

    /**
     * Erstellt einen Mock KPIValue für Tests.
     */
    private function createMockKPIValue(float $value): KPIValue
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $kpiValue->method('getValueAsFloat')->willReturn($value);
        
        return $kpiValue;
    }
}