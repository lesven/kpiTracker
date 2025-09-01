<?php

namespace App\Service;

use App\Entity\KPI;
use App\Repository\KPIValueRepository;
use App\Domain\ValueObject\Period;

/**
 * Service-Klasse für das Management und die Business-Logik von KPIs.
 *
 * Zentraler Service für KPI-bezogene Operationen und Statusberechnung.
 */
class KPIService
{
    public function __construct(
        private KPIValueRepository $kpiValueRepository,
        private KPIStatusService $kpiStatusService,
    ) {
    }

    /**
     * Delegiert an KPIStatusService für Kompatibilität.
     */
    public function getKpiStatus(KPI $kpi): string
    {
        return $this->kpiStatusService->getKpiStatus($kpi);
    }

    /**
     * Prüft ob für eine KPI bereits ein Wert für den aktuellen Zeitraum existiert.
     */
    public function hasCurrentValue(KPI $kpi): bool
    {
        $currentPeriod = new Period($kpi->getCurrentPeriod());
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

        return null !== $existingValue;
    }

    /**
     * Berechnet Statistiken für eine KPI.
     */
    public function getKpiStatistics(KPI $kpi): array
    {
        $values = $this->kpiValueRepository->findByKPI($kpi);

        if (empty($values)) {
            return [
                'total_entries' => 0,
                'average_value' => null,
                'min_value' => null,
                'max_value' => null,
                'latest_value' => null,
                'trend' => 'no_data',
            ];
        }

        $numericValues = array_map(fn ($v) => $v->getValueAsFloat(), $values);

        $stats = [
            'total_entries' => count($values),
            'average_value' => round(array_sum($numericValues) / count($numericValues), 2),
            'min_value' => min($numericValues),
            'max_value' => max($numericValues),
            'latest_value' => $values[0], // Array ist bereits sortiert (neueste zuerst)
            'trend' => $this->calculateTrend($numericValues),
        ];

        return $stats;
    }

    /**
     * Berechnet den Trend einer KPI basierend auf den letzten Werten.
     */
    private function calculateTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'insufficient_data';
        }

        // Letzten 3 Werte für Trend-Berechnung verwenden
        $recentValues = array_slice($values, 0, min(3, count($values)));

        if (count($recentValues) < 2) {
            return 'insufficient_data';
        }

        $first = $recentValues[count($recentValues) - 1];
        $last = $recentValues[0];

        $percentageChange = (($last - $first) / $first) * 100;

        if ($percentageChange > 5) {
            return 'rising';
        } elseif ($percentageChange < -5) {
            return 'falling';
        }

        return 'stable';
    }

    /**
     * Validiert KPI-Daten vor dem Speichern.
     */
    public function validateKpi(KPI $kpi): array
    {
        $errors = [];

        if (!$kpi->getName() || 0 === mb_strlen(mb_trim($kpi->getName()))) {
            $errors[] = 'KPI-Name ist erforderlich.';
        }

        if (null === $kpi->getInterval()) {
            $errors[] = 'Ungültiges Intervall gewählt.';
        }

        if (!$kpi->getUser()) {
            $errors[] = 'KPI muss einem Benutzer zugeordnet sein.';
        }

        return $errors;
    }
}
