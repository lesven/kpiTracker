<?php

namespace App\Factory;

use App\DTO\DashboardKpiEntry;
use App\Entity\KPI;
use App\Repository\KPIValueRepository;
use App\Service\KPIStatusService;

/**
 * Factory zur Erstellung von DashboardKpiEntry-DTOs für die Dashboard-Anzeige.
 *
 * Nutzt KPIStatusService und KPIValueRepository, um alle relevanten KPI-Daten für das Dashboard zusammenzustellen.
 */
class DashboardKpiEntryFactory
{
    /**
     * Konstruktor injiziert Status-Service und Value-Repository.
     *
     * @param KPIStatusService   $kpiStatusService   Service zur Ermittlung des KPI-Status
     * @param KPIValueRepository $kpiValueRepository Repository für KPI-Werte
     */
    public function __construct(
        private KPIStatusService $kpiStatusService,
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Erstellt ein DashboardKpiEntry-DTO für ein gegebenes KPI.
     *
     * @param KPI $kpi Das zu verarbeitende KPI-Objekt
     *
     * @return DashboardKpiEntry DTO mit Status, Wert und Fälligkeitsdaten
     */
    public function create(KPI $kpi): DashboardKpiEntry
    {
        return new DashboardKpiEntry(
            kpi: $kpi,
            status: $this->kpiStatusService->getKpiStatus($kpi),
            latestValue: $this->kpiValueRepository->findLatestValueForKpi($kpi),
            isDueSoon: $this->kpiStatusService->isDueSoon($kpi),
            isOverdue: $this->kpiStatusService->isOverdue($kpi),
            nextDueDate: $kpi->getNextDueDate(),
        );
    }
}
