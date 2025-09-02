<?php

namespace App\Service;

use App\Entity\KPI;

/**
 * Service-Klasse für die Berechnung des KPI-Status und Business-Logik.
 *
 * User Story 9: KPI-Dashboard mit Ampellogik (grün, gelb, rot).
 */
class KPIStatusService
{
    public function __construct(
        private KPIAggregate $kpiAggregate,
    ) {
    }

    public function getKpiStatus(KPI $kpi): string
    {
        return $this->kpiAggregate->getKpiStatus($kpi);
    }

    public function isDueSoon(KPI $kpi): bool
    {
        return $this->kpiAggregate->isDueSoon($kpi);
    }

    public function isOverdue(KPI $kpi): bool
    {
        return $this->kpiAggregate->isOverdue($kpi);
    }

    public function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        return $this->kpiAggregate->calculateDueDate($kpi);
    }

    public function getDaysOverdue(KPI $kpi): int
    {
        return $this->kpiAggregate->getDaysOverdue($kpi);
    }

    public function getKpisForReminder(array $kpis, int $daysBefore = 3, int $daysAfter = 0): array
    {
        return $this->kpiAggregate->getKpisForReminder($kpis, $daysBefore, $daysAfter);
    }
}
