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
    public function kannSteigendenTrendErstellen(): void
    {
        $trend = KPITrend::rising();

        $this->assertTrue($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertSame('rising', $trend->toString());
    }

    /**
     * @test
     */
    public function kannFallendenTrendErstellen(): void
    {
        $trend = KPITrend::falling();

        $this->assertFalse($trend->isRising());
        $this->assertTrue($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertSame('falling', $trend->toString());
    }

    /**
     * @test
     */
    public function kannStabilenTrendErstellen(): void
    {
        $trend = KPITrend::stable();

        $this->assertFalse($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertTrue($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertSame('stable', $trend->toString());
    }

    /**
     * @test
     */
    public function kannVolatilenTrendErstellen(): void
    {
        $trend = KPITrend::volatile();

        $this->assertFalse($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertTrue($trend->isVolatile());
        $this->assertFalse($trend->isNoData());
        $this->assertSame('volatile', $trend->toString());
    }

    /**
     * @test
     */
    public function kannKeineDatenTrendErstellen(): void
    {
        $trend = KPITrend::noData();

        $this->assertFalse($trend->isRising());
        $this->assertFalse($trend->isFalling());
        $this->assertFalse($trend->isStable());
        $this->assertFalse($trend->isVolatile());
        $this->assertTrue($trend->isNoData());
        $this->assertSame('no_data', $trend->toString());
    }

    /**
     * @test
     */
    public function kannTrendAusStringErstellen(): void
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
    public function wirftExceptionBeiUngueltigemString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid KPI trend: invalid');

        KPITrend::fromString('invalid');
    }

    /**
     * @test
     */
    public function kannTrendAusNumerischenWertenBerechnen(): void
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
    public function kannTrendAusKpiValuesBerechnen(): void
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
    public function kannErweiterteTrendAnalyseDurchfuehren(): void
    {
        // Trend mit Konfidenz und Details
        $values = [100.0, 105.0, 110.0, 120.0, 125.0];
        $trend = KPITrend::fromValuesWithAnalysis($values);

        $this->assertTrue($trend->isRising());
        $this->assertGreaterThan(80.0, $trend->getConfidence());
        $this->assertGreaterThan(10.0, $trend->getPercentageChange());
        $this->assertLessThan(20.0, $trend->getVolatility());
        $this->assertSame('consistent', $trend->getDirection());
    }

    /**
     * @test
     */
    public function kannTrendStaerkeBewerten(): void
    {
        // Starker steigender Trend
        $strongRising = KPITrend::fromValues([100.0, 120.0, 140.0, 160.0]);
        $this->assertSame('strong', $strongRising->getStrength());
        $this->assertTrue($strongRising->isStrong());

        // Schwacher Trend
        $weakTrend = KPITrend::fromValues([100.0, 103.0, 101.0, 104.0]);
        $this->assertSame('weak', $weakTrend->getStrength());
        $this->assertFalse($weakTrend->isStrong());

        // Moderater Trend
        $moderateTrend = KPITrend::fromValues([100.0, 110.0, 115.0, 120.0]);
        $this->assertSame('moderate', $moderateTrend->getStrength());
    }

    /**
     * @test
     */
    public function kannTrendPrognoseErstellen(): void
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
    public function kannTrendUmkehrpunkteErkennen(): void
    {
        // Trend mit Umkehrpunkt
        $valuesWithTurning = [100.0, 110.0, 120.0, 110.0, 100.0];
        $trend = KPITrend::fromValuesWithAnalysis($valuesWithTurning);

        $this->assertTrue($trend->hasTurningPoint());
        $this->assertSame(2, $trend->getTurningPointIndex()); // Bei Index 2 (120.0)
    }

    /**
     * @test
     */
    public function kannBenutzerfreundlicheBeschreibungGenerieren(): void
    {
        $risingTrend = KPITrend::rising();
        $this->assertSame('Steigend', $risingTrend->getDescription());
        $this->assertSame('📈', $risingTrend->getEmoji());

        $fallingTrend = KPITrend::falling();
        $this->assertSame('Fallend', $fallingTrend->getDescription());
        $this->assertSame('📉', $fallingTrend->getEmoji());

        $stableTrend = KPITrend::stable();
        $this->assertSame('Stabil', $stableTrend->getDescription());
        $this->assertSame('➡️', $stableTrend->getEmoji());

        $volatileTrend = KPITrend::volatile();
        $this->assertSame('Schwankend', $volatileTrend->getDescription());
        $this->assertSame('📊', $volatileTrend->getEmoji());

        $noDataTrend = KPITrend::noData();
        $this->assertSame('Keine Daten', $noDataTrend->getDescription());
        $this->assertSame('❓', $noDataTrend->getEmoji());
    }

    /**
     * @test
     */
    public function kannCssKlasseFuerStylingAbrufen(): void
    {
        $rising = KPITrend::rising();
        $falling = KPITrend::falling();
        $stable = KPITrend::stable();

        $this->assertSame('trend-rising', $rising->getCssClass());
        $this->assertSame('trend-falling', $falling->getCssClass());
        $this->assertSame('trend-stable', $stable->getCssClass());
    }

    /**
     * @test
     */
    public function kannDetaillierteAnalyseAusgeben(): void
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
    public function kannJsonSerialisierungHandhaben(): void
    {
        $trend = KPITrend::rising();

        $json = json_encode($trend);
        $this->assertSame('"rising"', $json);

        $decoded = json_decode($json, true);
        $recreated = KPITrend::fromString($decoded);

        $this->assertTrue($trend->equals($recreated));
    }

    /**
     * @test
     */
    public function kannGleichheitPruefen(): void
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
    public function valueObjectIstImmutable(): void
    {
        $trend = KPITrend::rising();
        $originalString = $trend->toString();

        // "Änderung" durch Erstellen eines neuen Trends
        $newTrend = KPITrend::falling();

        // Original-Trend sollte unverändert bleiben
        $this->assertSame('rising', $originalString);
        $this->assertSame('rising', $trend->toString());
        $this->assertSame('falling', $newTrend->toString());
    }

    /**
     * @test
     */
    public function kannStringDarstellungAusgeben(): void
    {
        $rising = KPITrend::rising();
        $stable = KPITrend::stable();

        $this->assertSame('rising', (string) $rising);
        $this->assertSame('stable', (string) $stable);
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
