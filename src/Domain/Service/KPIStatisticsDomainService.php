<?php

namespace App\Domain\Service;

use App\Domain\ValueObject\KPIStatistics;
use App\Domain\ValueObject\KPITrend;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;

/**
 * Domain Service für komplexe KPI-Statistik-Berechnungen.
 *
 * Kapselt mathematische Algorithmen für:
 * - Deskriptive Statistiken (Durchschnitt, Median, Modus)
 * - Dispersionsmaße (Varianz, Standardabweichung, Quartile)
 * - Trend-Analysen und Korrelationen
 * - Ausreißer-Erkennung und Datenqualitätsprüfungen
 * - Performance-Benchmarking und Forecasting
 */
class KPIStatisticsDomainService
{
    /**
     * Minimale Anzahl Datenpunkte für zuverlässige Statistiken.
     */
    private const MIN_DATA_POINTS_FOR_STATISTICS = 3;
    private const MIN_DATA_POINTS_FOR_TREND = 2;

    public function __construct(
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Berechnet umfassende Statistiken für eine KPI.
     *
     * @param KPI $kpi Die zu analysierende KPI
     * @param array $options Konfigurationsoptionen für die Berechnung
     * @return KPIStatistics Vollständige statistische Auswertung
     */
    public function calculateStatistics(KPI $kpi, array $options = []): KPIStatistics
    {
        $values = $this->kpiValueRepository->findByKPI($kpi);

        if (empty($values)) {
            return KPIStatistics::empty();
        }

        $numericValues = $this->extractNumericValues($values);
        $includeAdvanced = $options['include_advanced'] ?? true;

        $basicStats = $this->calculateBasicStatistics($numericValues);
        $trend = $this->calculateTrend($numericValues);
        
        $latestValue = $values[0] ?? null; // Repository sortiert bereits chronologisch
        $oldestValue = end($values) ?: null;

        if ($includeAdvanced && count($numericValues) >= self::MIN_DATA_POINTS_FOR_STATISTICS) {
            $advancedStats = $this->calculateAdvancedStatistics($numericValues);
            
            return new KPIStatistics(
                totalEntries: count($values),
                averageValue: $basicStats['average'],
                minValue: $basicStats['min'],
                maxValue: $basicStats['max'],
                latestValue: $latestValue,
                oldestValue: $oldestValue,
                trend: $trend,
                variance: $advancedStats['variance'],
                standardDeviation: $advancedStats['std_deviation'],
            );
        }

        return KPIStatistics::fromBasicData(
            totalEntries: count($values),
            averageValue: $basicStats['average'],
            minValue: $basicStats['min'],
            maxValue: $basicStats['max'],
            latestValue: $latestValue,
            trend: $trend,
        );
    }

    /**
     * Berechnet erweiterte Trend-Analyse mit Konfidenz-Intervallen.
     *
     * @param array $values Array von KPIValue Objekten
     * @param int $analysisWindow Anzahl der letzten Werte für Analyse
     * @return KPITrend Detaillierte Trend-Analyse
     */
    public function calculateDetailedTrend(array $values, int $analysisWindow = 5): KPITrend
    {
        $numericValues = $this->extractNumericValues($values);
        
        if (count($numericValues) < self::MIN_DATA_POINTS_FOR_TREND) {
            return KPITrend::noData();
        }

        // Nur die letzten X Werte für Trend-Analyse verwenden
        $recentValues = array_slice($numericValues, 0, min($analysisWindow, count($numericValues)));
        
        if (count($recentValues) < 2) {
            return KPITrend::noData();
        }

        $trendData = $this->performTrendAnalysis($recentValues);
        
        return KPITrend::fromData(
            percentageChange: $trendData['percentage_change'],
            volatility: $trendData['volatility'],
            dataPoints: count($recentValues),
            confidence: $trendData['confidence'],
        );
    }

    /**
     * Identifiziert statistische Ausreißer in KPI-Daten.
     *
     * @param KPI $kpi Die zu analysierende KPI
     * @param float $threshold Z-Score Schwellwert für Ausreißer (Standard: 2.0)
     * @return array Liste der KPIValues die als Ausreißer identifiziert wurden
     */
    public function detectOutliers(KPI $kpi, float $threshold = 2.0): array
    {
        $values = $this->kpiValueRepository->findByKPI($kpi);
        
        if (count($values) < self::MIN_DATA_POINTS_FOR_STATISTICS) {
            return [];
        }

        $numericValues = $this->extractNumericValues($values);
        $mean = array_sum($numericValues) / count($numericValues);
        $stdDev = $this->calculateStandardDeviation($numericValues, $mean);

        if ($stdDev == 0) {
            return []; // Keine Streuung = keine Ausreißer
        }

        $outliers = [];
        
        foreach ($values as $index => $value) {
            $numericValue = $numericValues[$index];
            $zScore = abs(($numericValue - $mean) / $stdDev);
            
            if ($zScore > $threshold) {
                $outliers[] = [
                    'value' => $value,
                    'z_score' => $zScore,
                    'deviation' => $numericValue - $mean,
                ];
            }
        }

        return $outliers;
    }

    /**
     * Berechnet Korrelation zwischen zwei KPIs.
     *
     * @param KPI $kpi1 Erste KPI
     * @param KPI $kpi2 Zweite KPI
     * @return array Korrelationsanalyse
     */
    public function calculateCorrelation(KPI $kpi1, KPI $kpi2): array
    {
        $values1 = $this->kpiValueRepository->findByKPI($kpi1);
        $values2 = $this->kpiValueRepository->findByKPI($kpi2);

        // Zeitraum-basierte Zuordnung der Werte
        $pairedValues = $this->pairValuesByPeriod($values1, $values2);
        
        if (count($pairedValues) < self::MIN_DATA_POINTS_FOR_STATISTICS) {
            return [
                'correlation_coefficient' => null,
                'strength' => 'insufficient_data',
                'paired_data_points' => count($pairedValues),
            ];
        }

        $correlation = $this->calculatePearsonCorrelation($pairedValues);

        return [
            'correlation_coefficient' => $correlation,
            'strength' => $this->interpretCorrelationStrength($correlation),
            'paired_data_points' => count($pairedValues),
            'relationship' => $correlation > 0 ? 'positive' : ($correlation < 0 ? 'negative' : 'none'),
        ];
    }

    /**
     * Erstellt Performance-Benchmark basierend auf historischen Daten.
     *
     * @param KPI $kpi Die zu analysierende KPI
     * @param int $benchmarkMonths Anzahl Monate für Benchmark-Berechnung
     * @return array Benchmark-Daten
     */
    public function createPerformanceBenchmark(KPI $kpi, int $benchmarkMonths = 12): array
    {
        $cutoffDate = new \DateTimeImmutable("-{$benchmarkMonths} months");
        $values = $this->kpiValueRepository->findByKPISince($kpi, $cutoffDate);
        
        if (empty($values)) {
            return ['status' => 'no_historical_data'];
        }

        $numericValues = $this->extractNumericValues($values);
        $statistics = $this->calculateBasicStatistics($numericValues);
        
        return [
            'benchmark_period' => $benchmarkMonths,
            'data_points' => count($values),
            'performance_targets' => [
                'excellent' => $statistics['max'],
                'good' => $statistics['average'] + ($statistics['std_dev'] ?? 0),
                'average' => $statistics['average'],
                'below_average' => $statistics['average'] - ($statistics['std_dev'] ?? 0),
                'poor' => $statistics['min'],
            ],
            'baseline_stats' => $statistics,
        ];
    }

    /**
     * Prognostiziert zukünftige KPI-Werte basierend auf historischen Trends.
     * Einfache lineare Regression für Basis-Forecasting.
     *
     * @param KPI $kpi Die zu prognostizierende KPI
     * @param int $forecastPeriods Anzahl zukünftiger Perioden
     * @return array Prognose-Ergebnisse
     */
    public function forecastValues(KPI $kpi, int $forecastPeriods = 3): array
    {
        $values = $this->kpiValueRepository->findByKPI($kpi);
        
        if (count($values) < 3) {
            return ['status' => 'insufficient_data_for_forecast'];
        }

        $numericValues = $this->extractNumericValues($values);
        $trendData = $this->performLinearRegression($numericValues);
        
        if ($trendData['confidence'] < 0.5) {
            return ['status' => 'low_confidence_forecast', 'confidence' => $trendData['confidence']];
        }

        $forecasts = [];
        $lastIndex = count($numericValues);
        
        for ($i = 1; $i <= $forecastPeriods; $i++) {
            $forecastValue = $trendData['intercept'] + ($trendData['slope'] * ($lastIndex + $i));
            $forecasts[] = [
                'period' => $i,
                'predicted_value' => round($forecastValue, 2),
                'confidence_interval' => [
                    'lower' => round($forecastValue - $trendData['margin_of_error'], 2),
                    'upper' => round($forecastValue + $trendData['margin_of_error'], 2),
                ],
            ];
        }

        return [
            'status' => 'success',
            'model_confidence' => $trendData['confidence'],
            'trend_direction' => $trendData['slope'] > 0 ? 'increasing' : 'decreasing',
            'forecasts' => $forecasts,
        ];
    }

    /**
     * Extrahiert numerische Werte aus KPIValue-Objekten.
     */
    private function extractNumericValues(array $values): array
    {
        return array_map(fn (KPIValue $value) => $value->getValueAsFloat(), $values);
    }

    /**
     * Berechnet Basis-Statistiken.
     */
    private function calculateBasicStatistics(array $values): array
    {
        if (empty($values)) {
            return ['average' => null, 'min' => null, 'max' => null];
        }

        $count = count($values);
        $sum = array_sum($values);
        
        return [
            'average' => round($sum / $count, 2),
            'min' => min($values),
            'max' => max($values),
            'count' => $count,
        ];
    }

    /**
     * Berechnet erweiterte Statistiken.
     */
    private function calculateAdvancedStatistics(array $values): array
    {
        $mean = array_sum($values) / count($values);
        $variance = $this->calculateVariance($values, $mean);
        
        return [
            'variance' => $variance,
            'std_deviation' => sqrt($variance),
            'median' => $this->calculateMedian($values),
        ];
    }

    /**
     * Berechnet die Varianz.
     */
    private function calculateVariance(array $values, float $mean): float
    {
        $squaredDifferences = array_map(fn ($value) => pow($value - $mean, 2), $values);
        return array_sum($squaredDifferences) / count($values);
    }

    /**
     * Berechnet die Standardabweichung.
     */
    private function calculateStandardDeviation(array $values, float $mean): float
    {
        return sqrt($this->calculateVariance($values, $mean));
    }

    /**
     * Berechnet den Median.
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        return $count % 2 === 0 
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }

    /**
     * Berechnet einfachen Trend basierend auf ersten und letzten Werten.
     */
    private function calculateTrend(array $values): KPITrend
    {
        if (count($values) < 2) {
            return KPITrend::noData();
        }

        $recentValues = array_slice($values, 0, min(3, count($values)));
        $first = end($recentValues);
        $last = $recentValues[0];

        if ($first == 0) {
            return KPITrend::stable();
        }

        $percentageChange = (($last - $first) / $first) * 100;

        return KPITrend::fromData($percentageChange, 0.0, count($recentValues));
    }

    /**
     * Führt erweiterte Trend-Analyse durch.
     */
    private function performTrendAnalysis(array $values): array
    {
        $count = count($values);
        $first = end($values);
        $last = $values[0];
        
        $percentageChange = $first != 0 ? (($last - $first) / $first) * 100 : 0;
        
        // Volatilität basierend auf Standardabweichung
        $mean = array_sum($values) / $count;
        $volatility = $count > 1 ? $this->calculateStandardDeviation($values, $mean) : 0.0;
        
        // Confidence basierend auf Anzahl Datenpunkte und Konsistenz
        $confidence = min(1.0, ($count / 10) * (1 - min($volatility / $mean, 1.0)));
        
        return [
            'percentage_change' => $percentageChange,
            'volatility' => $volatility,
            'confidence' => $confidence,
        ];
    }

    /**
     * Führt lineare Regression für Forecasting durch.
     */
    private function performLinearRegression(array $values): array
    {
        $n = count($values);
        $xValues = range(1, $n);
        
        $sumX = array_sum($xValues);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumXSquared = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $xValues[$i] * $values[$i];
            $sumXSquared += $xValues[$i] * $xValues[$i];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXSquared - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // R-squared für Konfidenz-Berechnung
        $rSquared = $this->calculateRSquared($values, $xValues, $slope, $intercept);
        
        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'confidence' => sqrt($rSquared),
            'margin_of_error' => abs($slope * 0.2), // 20% Unsicherheit
        ];
    }

    /**
     * Berechnet R-squared für Regression.
     */
    private function calculateRSquared(array $yValues, array $xValues, float $slope, float $intercept): float
    {
        $yMean = array_sum($yValues) / count($yValues);
        $totalSumSquares = 0;
        $residualSumSquares = 0;
        
        for ($i = 0; $i < count($yValues); $i++) {
            $predicted = $slope * $xValues[$i] + $intercept;
            $totalSumSquares += pow($yValues[$i] - $yMean, 2);
            $residualSumSquares += pow($yValues[$i] - $predicted, 2);
        }
        
        return $totalSumSquares > 0 ? 1 - ($residualSumSquares / $totalSumSquares) : 0;
    }

    /**
     * Paart KPI-Werte basierend auf Zeiträumen für Korrelations-Analyse.
     */
    private function pairValuesByPeriod(array $values1, array $values2): array
    {
        $pairedValues = [];
        $values2ByPeriod = [];
        
        // Index für values2 nach Periode erstellen
        foreach ($values2 as $value) {
            $period = $value->getPeriod()?->value();
            if ($period) {
                $values2ByPeriod[$period] = $value->getValueAsFloat();
            }
        }
        
        // Pairing durchführen
        foreach ($values1 as $value1) {
            $period = $value1->getPeriod()?->value();
            if ($period && isset($values2ByPeriod[$period])) {
                $pairedValues[] = [
                    'x' => $value1->getValueAsFloat(),
                    'y' => $values2ByPeriod[$period],
                ];
            }
        }
        
        return $pairedValues;
    }

    /**
     * Berechnet Pearson-Korrelationskoeffizient.
     */
    private function calculatePearsonCorrelation(array $pairedValues): ?float
    {
        $n = count($pairedValues);
        
        if ($n < 3) {
            return null;
        }
        
        $xValues = array_column($pairedValues, 'x');
        $yValues = array_column($pairedValues, 'y');
        
        $xMean = array_sum($xValues) / $n;
        $yMean = array_sum($yValues) / $n;
        
        $numerator = 0;
        $xSumSquares = 0;
        $ySumSquares = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $xDeviation = $xValues[$i] - $xMean;
            $yDeviation = $yValues[$i] - $yMean;
            
            $numerator += $xDeviation * $yDeviation;
            $xSumSquares += $xDeviation * $xDeviation;
            $ySumSquares += $yDeviation * $yDeviation;
        }
        
        $denominator = sqrt($xSumSquares * $ySumSquares);
        
        return $denominator > 0 ? $numerator / $denominator : null;
    }

    /**
     * Interpretiert Korrelations-Stärke.
     */
    private function interpretCorrelationStrength(?float $correlation): string
    {
        if ($correlation === null) {
            return 'unknown';
        }
        
        $abs = abs($correlation);
        
        return match (true) {
            $abs >= 0.9 => 'very_strong',
            $abs >= 0.7 => 'strong',
            $abs >= 0.5 => 'moderate',
            $abs >= 0.3 => 'weak',
            default => 'very_weak',
        };
    }
}