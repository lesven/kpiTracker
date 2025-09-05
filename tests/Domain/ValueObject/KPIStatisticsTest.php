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
    public function kannLeereStatistikErstellen(): void
    {
        $stats = KPIStatistics::empty();

        $this->assertSame(0, $stats->getTotalEntries());
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
    public function kannStatistikMitEinzelwertErstellen(): void
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

        $this->assertSame(1, $stats->getTotalEntries());
        $this->assertSame(100.0, $stats->getAverageValue());
        $this->assertSame(100.0, $stats->getMinValue());
        $this->assertSame(100.0, $stats->getMaxValue());
        $this->assertSame($kpiValue, $stats->getLatestValue());
        $this->assertTrue($stats->hasData());
        $this->assertFalse($stats->isEmpty());
    }

    /**
     * @test
     */
    public function kannStatistikMitMehrerenWertenErstellen(): void
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

        $this->assertSame(5, $stats->getTotalEntries());
        $this->assertSame(120.0, $stats->getAverageValue());
        $this->assertSame(50.0, $stats->getMinValue());
        $this->assertSame(200.0, $stats->getMaxValue());
        $this->assertSame($latestValue, $stats->getLatestValue());
        $this->assertTrue($stats->getTrend()->isRising());
    }

    /**
     * @test
     */
    public function kannErweiterteStatistikenBerechnen(): void
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
        $this->assertSame(40.0, $stats->getRange());

        // Coefficient of Variation (20% Standardabweichung angenommen)
        $this->assertSame(20.0, $stats->getCoefficientOfVariation());

        // Stability Rating basierend auf CV
        $this->assertSame('moderate', $stats->getStabilityRating());
    }

    /**
     * @test
     */
    public function kannStabilityRatingKorrektBerechnen(): void
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
        $this->assertSame('high', $stableStats->getStabilityRating());

        // Mäßig stabil (10% <= CV < 25%)
        $moderateStats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 100.0,
            minValue: 80.0,
            maxValue: 120.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::stable()
        );
        $this->assertSame('moderate', $moderateStats->getStabilityRating());

        // Instabil (CV >= 25%)
        $unstableStats = KPIStatistics::fromData(
            totalEntries: 10,
            averageValue: 100.0,
            minValue: 50.0,
            maxValue: 150.0,
            latestValue: $this->createMockKPIValue(100.0),
            trend: KPITrend::volatile()
        );
        $this->assertSame('low', $unstableStats->getStabilityRating());
    }

    /**
     * @test
     */
    public function kannPerformanceGegenZielwertBewerten(): void
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
        $this->assertSame(20.0, $stats->getPerformanceVsTarget(100.0));
        $this->assertSame('above_target', $stats->getPerformanceRating(100.0));

        // Genau Zielwert
        $this->assertSame(0.0, $stats->getPerformanceVsTarget(120.0));
        $this->assertSame('on_target', $stats->getPerformanceRating(120.0));

        // Unter Zielwert
        $this->assertSame(-16.67, $stats->getPerformanceVsTarget(150.0));
        $this->assertSame('below_target', $stats->getPerformanceRating(150.0));
    }

    /**
     * @test
     */
    public function kannArrayDarstellungAusgeben(): void
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
        $this->assertSame(5, $array['total_entries']);
        $this->assertSame(100.0, $array['average_value']);
        $this->assertSame(80.0, $array['min_value']);
        $this->assertSame(120.0, $array['max_value']);
        $this->assertSame($kpiValue, $array['latest_value']);
        $this->assertSame('rising', $array['trend']);
        $this->assertSame(40.0, $array['range']);
        $this->assertSame(20.0, $array['coefficient_of_variation']);
        $this->assertSame('moderate', $array['stability_rating']);
    }

    /**
     * @test
     */
    public function kannJsonSerialisierungHandhaben(): void
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
        $this->assertSame(3, $decoded['total_entries']);
        $this->assertSame(100.0, $decoded['average_value']);
    }

    /**
     * @test
     */
    public function kannZusammenfassungGenerieren(): void
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
    public function kannOutlierErkennen(): void
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
    public function kannQualitaetsbewertungErstellen(): void
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
        $this->assertSame('high', $highQuality->getDataQualityRating());

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
        $this->assertSame('low', $lowQuality->getDataQualityRating());
    }

    /**
     * @test
     */
    public function valueObjectIstImmutable(): void
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
        $this->assertSame(5, $originalStats->getTotalEntries());
        $this->assertSame(100.0, $originalStats->getAverageValue());
        $this->assertSame(10, $newStats->getTotalEntries());
        $this->assertSame(200.0, $newStats->getAverageValue());
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
