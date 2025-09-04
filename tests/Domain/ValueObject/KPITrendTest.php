<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\KPITrend;
use App\Entity\KPIValue;
use PHPUnit\Framework\TestCase;

/**
 * Test für das KPITrend Value Object.
 *
 * Testet alle Trend-Berechnungen, Factory-Methoden und
 * Business Logic für Trend-Analysen.
 */
class KPITrendTest extends TestCase
{
    /**
     * @test
     */
    public function kann_steigenden_trend_erstellen(): void
    {
        $trend = KPITrend::rising();

        $this->assertTrue($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertEquals('rising', $trend->toString());
    }

    /**
     * @test
     */
    public function kann_fallenden_trend_erstellen(): void
    {
        $trend = KPITrend::falling();

        $this->assertFalse($trend->isRising());
        $this->assertTrue($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertEquals('falling', $trend->toString());
    }

    /**
     * @test
     */
    public function kann_stabilen_trend_erstellen(): void
    {
        $trend = KPITrend::stable();

        $this->assertFalse($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertTrue($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertEquals('stable', $trend->toString());
    }

    /**
     * @test
     */
    public function kann_volatilen_trend_erstellen(): void
    {
        $trend = KPITrend::volatile();

        $this->assertFalse($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertTrue($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertEquals('volatile', $trend->toString());
    }

    /**
     * @test
     */
    public function kann_keine_daten_trend_erstellen(): void
    {
        $trend = KPITrend::noData();

        $this->assertFalse($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertTrue($trend->isNoData());
        $this->assertEquals('no_data', $trend->toString());
    }

    /**
     * @test
     */
    public function kann_trend_aus_string_erstellen(): void
    {
        $rising = KPITrend::fromString('rising');
        $falling = KPITrend::fromString('falling');
        $stable = KPITrend::fromString('stable');
        $volatile = KPITrend::fromString('volatile');
        $noData = KPITrend::fromString('no_data');

        $this->assertTrue($rising->isRising());
        $this->assertTrue($falling->isFalling());
        $this->assertTrue($stable->isStable());
        $this->assertTrue($volatile->isVolatile());
        $this->assertTrue($noData->isNoData());
    }

    /**
     * @test
     */
    public function wirft_exception_bei_ungueltigem_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid KPI trend: invalid');

        KPITrend::fromString('invalid');
    }

    /**
     * @test
     */
    public function kann_trend_aus_numerischen_werten_berechnen(): void
    {
        // Steigende Werte (>5% Änderung)
        $risingValues = [80.0, 85.0, 90.0, 100.0];
        $risingTrend = KPITrend::fromValues($risingValues);
        $this->assertTrue($risingTrend->isRising());

        // Fallende Werte (<-5% Änderung)
        $fallingValues = [100.0, 90.0, 85.0, 80.0];
        $fallingTrend = KPITrend::fromValues($fallingValues);
        $this->assertTrue($fallingTrend->isFalling());

        // Stabile Werte (-5% bis +5% Änderung)
        $stableValues = [100.0, 102.0, 98.0, 101.0];
        $stableTrend = KPITrend::fromValues($stableValues);
        $this->assertTrue($stableTrend->isStable());

        // Volatile Werte (hohe Schwankungen)
        $volatileValues = [100.0, 150.0, 50.0, 120.0, 80.0];
        $volatileTrend = KPITrend::fromValues($volatileValues);
        $this->assertTrue($volatileTrend->isVolatile());

        // Zu wenige Daten
        $noDataValues = [100.0];
        $noDataTrend = KPITrend::fromValues($noDataValues);
        $this->assertTrue($noDataTrend->isNoData());
    }

    /**
     * @test
     */
    public function kann_trend_aus_kpi_values_berechnen(): void
    {
        $values = [
            $this->createMockKPIValue(80.0),
            $this->createMockKPIValue(85.0),
            $this->createMockKPIValue(90.0),
            $this->createMockKPIValue(100.0),
        ];

        $trend = KPITrend::fromKPIValues($values);
        $this->assertTrue($trend->isRising());
    }

    /**
     * @test
     */
    public function kann_erweiterte_trend_analyse_durchfuehren(): void
    {
        // Trend mit Konfidenz und Details
        $values = [100.0, 105.0, 110.0, 120.0, 125.0];
        $trend = KPITrend::fromValuesWithAnalysis($values);

        $this->assertTrue($trend->isRising());
        $this->assertGreaterThan(80.0, $trend->getConfidence());
        $this->assertGreaterThan(10.0, $trend->getPercentageChange());
        $this->assertLessThan(20.0, $trend->getVolatility());
        $this->assertEquals('consistent', $trend->getDirection());
    }

    /**
     * @test
     */
    public function kann_trend_staerke_bewerten(): void
    {
        // Starker steigender Trend
        $strongRising = KPITrend::fromValues([100.0, 120.0, 140.0, 160.0]);
        $this->assertEquals('strong', $strongRising->getStrength());
        $this->assertTrue($strongRising->isStrong());

        // Schwacher Trend
        $weakTrend = KPITrend::fromValues([100.0, 103.0, 101.0, 104.0]);
        $this->assertEquals('weak', $weakTrend->getStrength());
        $this->assertFalse($weakTrend->isStrong());

        // Moderater Trend
        $moderateTrend = KPITrend::fromValues([100.0, 110.0, 115.0, 120.0]);
        $this->assertEquals('moderate', $moderateTrend->getStrength());
    }

    /**
     * @test
     */
    public function kann_trend_prognose_erstellen(): void
    {
        $values = [100.0, 110.0, 120.0, 130.0, 140.0];
        $trend = KPITrend::fromValuesWithAnalysis($values);

        $nextValue = $trend->predictNextValue();
        $this->assertGreaterThan(140.0, $nextValue);
        $this->assertLessThan(160.0, $nextValue); // Realistischer Bereich

        $futureValues = $trend->predictFutureValues(3);
        $this->assertCount(3, $futureValues);
        $this->assertGreaterThan($futureValues[0], $futureValues[1]); // Steigende Prognose
    }

    /**
     * @test
     */
    public function kann_trend_umkehrpunkte_erkennen(): void
    {
        // Trend mit Umkehrpunkt
        $valuesWithTurning = [100.0, 110.0, 120.0, 110.0, 100.0];
        $trend = KPITrend::fromValuesWithAnalysis($valuesWithTurning);

        $this->assertTrue($trend->hasTurningPoint());
        $this->assertEquals(2, $trend->getTurningPointIndex()); // Bei Index 2 (120.0)
    }

    /**
     * @test
     */
    public function kann_benutzerfreundliche_beschreibung_generieren(): void
    {
        $risingTrend = KPITrend::rising();
        $this->assertEquals('Steigend', $risingTrend->getDescription());
        $this->assertEquals('📈', $risingTrend->getEmoji());

        $fallingTrend = KPITrend::falling();
        $this->assertEquals('Fallend', $fallingTrend->getDescription());
        $this->assertEquals('📉', $fallingTrend->getEmoji());

        $stableTrend = KPITrend::stable();
        $this->assertEquals('Stabil', $stableTrend->getDescription());
        $this->assertEquals('➡️', $stableTrend->getEmoji());

        $volatileTrend = KPITrend::volatile();
        $this->assertEquals('Schwankend', $volatileTrend->getDescription());
        $this->assertEquals('📊', $volatileTrend->getEmoji());

        $noDataTrend = KPITrend::noData();
        $this->assertEquals('Keine Daten', $noDataTrend->getDescription());
        $this->assertEquals('❓', $noDataTrend->getEmoji());
    }

    /**
     * @test
     */
    public function kann_css_klasse_fuer_styling_abrufen(): void
    {
        $rising = KPITrend::rising();
        $falling = KPITrend::falling();
        $stable = KPITrend::stable();

        $this->assertEquals('trend-rising', $rising->getCssClass());
        $this->assertEquals('trend-falling', $falling->getCssClass());
        $this->assertEquals('trend-stable', $stable->getCssClass());
    }

    /**
     * @test
     */
    public function kann_detaillierte_analyse_ausgeben(): void
    {
        $values = [100.0, 105.0, 115.0, 110.0, 120.0];
        $trend = KPITrend::fromValuesWithAnalysis($values);

        $analysis = $trend->getDetailedAnalysis();

        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('trend_type', $analysis);
        $this->assertArrayHasKey('confidence', $analysis);
        $this->assertArrayHasKey('percentage_change', $analysis);
        $this->assertArrayHasKey('volatility', $analysis);
        $this->assertArrayHasKey('strength', $analysis);
        $this->assertArrayHasKey('prediction', $analysis);
    }

    /**
     * @test
     */
    public function kann_json_serialisierung_handhaben(): void
    {
        $trend = KPITrend::rising();

        $json = json_encode($trend);
        $this->assertEquals('"rising"', $json);

        $decoded = json_decode($json, true);
        $recreated = KPITrend::fromString($decoded);
        
        $this->assertTrue($trend->equals($recreated));
    }

    /**
     * @test
     */
    public function kann_gleichheit_pruefen(): void
    {
        $rising1 = KPITrend::rising();
        $rising2 = KPITrend::rising();
        $falling = KPITrend::falling();

        $this->assertTrue($rising1->equals($rising2));
        $this->assertFalse($rising1->equals($falling));
    }

    /**
     * @test
     */
    public function value_object_ist_immutable(): void
    {
        $trend = KPITrend::rising();
        $originalString = $trend->toString();

        // "Änderung" durch Erstellen eines neuen Trends
        $newTrend = KPITrend::falling();

        // Original-Trend sollte unverändert bleiben
        $this->assertEquals('rising', $originalString);
        $this->assertEquals('rising', $trend->toString());
        $this->assertEquals('falling', $newTrend->toString());
    }

    /**
     * @test
     */
    public function kann_string_darstellung_ausgeben(): void
    {
        $rising = KPITrend::rising();
        $stable = KPITrend::stable();

        $this->assertEquals('rising', (string) $rising);
        $this->assertEquals('stable', (string) $stable);
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