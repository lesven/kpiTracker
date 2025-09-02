<?php

namespace App\Factory;

use App\DTO\DashboardKpiEntry;
use App\Entity\KPI;
use App\Repository\KPIValueRepository;
use App\Service\KPIStatusService;

class DashboardKpiEntryFactory
{
    public function __construct(
        private KPIStatusService $kpiStatusService,
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

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
