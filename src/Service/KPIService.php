<?php

namespace App\Service;

use App\Entity\KPI;

/**
 * Service-Klasse für das Management und die Business-Logik von KPIs.
 *
 * Zentraler Service für KPI-bezogene Operationen und Statusberechnung.
 */
class KPIService
{
    public function __construct(
        private KPIAggregate $kpiAggregate,
    ) {
    }

    public function getKpiStatus(KPI $kpi): string
    {
        return $this->kpiAggregate->getKpiStatus($kpi);
    }

    public function hasCurrentValue(KPI $kpi): bool
    {
        return $this->kpiAggregate->hasCurrentValue($kpi);
    }

    public function getKpiStatistics(KPI $kpi): array
    {
        return $this->kpiAggregate->getKpiStatistics($kpi);
    }

    public function validateKpi(KPI $kpi): array
    {
        return $this->kpiAggregate->validateKpi($kpi);
    }
}
