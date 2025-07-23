<?php

namespace App\Service;

use App\Entity\KPI;
use App\Entity\User;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;

/**
 * Service zur Aufbereitung der Dashboard-Daten.
 */
class DashboardService
{
    /**
     * Konstruktor injiziert benötigte Repository- und Service-Klassen.
     */
    public function __construct(
        private KPIRepository $kpiRepository,
        private KPIValueRepository $kpiValueRepository,
        private KPIStatusService $kpiStatusService,
    ) {
    }

    /**
     * Liefert die KPI-Daten für das Dashboard eines Benutzers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKpiDataForUser(User $user): array
    {
        $kpis = $this->kpiRepository->findByUser($user);

        $data = array_map(
            function (KPI $kpi): array {
                return $this->createKpiEntry($kpi);
            },
            $kpis
        );

        usort($data, $this->compareDueDate(...));

        return $data;
    }

    /**
     * Erstellt Statistiken für das Dashboard.
     *
     * @param array<int, array<string, mixed>> $kpiData
     * @return array<string, mixed> An array containing:
     *                              - 'total_kpis' (int): Total number of KPIs.
     *                              - 'overdue_count' (int): Count of overdue KPIs.
     *                              - 'due_soon_count' (int): Count of KPIs due soon.
     *                              - 'up_to_date_count' (int): Count of up-to-date KPIs.
     *                              - 'recent_values' (array): Recent KPI values.
     */
    public function getDashboardStats(User $user, array $kpiData): array
    {
        return [
            'total_kpis' => count($kpiData),
            'overdue_count' => count(array_filter($kpiData, fn ($item) => 'red' === $item['status'])),
            'due_soon_count' => count(array_filter($kpiData, fn ($item) => 'yellow' === $item['status'])),
            'up_to_date_count' => count(array_filter($kpiData, fn ($item) => 'green' === $item['status'])),
            'recent_values' => $this->kpiValueRepository->findRecentByUser($user, 5),
        ];
    }

    /**
     * Zählt KPIs pro Status für Live-Updates.
     */
    /**
     * Provides a summary of KPI statuses for a given user.
     *
     * @param User $user The user whose KPIs are being summarized.
     *
     * @return array<string, int> An associative array with keys 'green', 'yellow', and 'red',
     *                            representing the count of KPIs in each status.
     */
    public function getStatusSummaryForUser(User $user): array
    {
        $kpis = $this->kpiRepository->findByUser($user);

        $summary = ['green' => 0, 'yellow' => 0, 'red' => 0];

        foreach ($kpis as $kpi) {
            $status = $this->kpiStatusService->getKpiStatus($kpi);
            ++$summary[$status];
        }

        return $summary;
    }

    /**
     * Erstellt das Array für eine einzelne KPI.
     */
    private function createKpiEntry(KPI $kpi): array
    {
        return [
            'kpi' => $kpi,
            'status' => $this->kpiStatusService->getKpiStatus($kpi),
            'latest_value' => $this->kpiValueRepository->findLatestValueForKpi($kpi),
            'is_due_soon' => $this->kpiStatusService->isDueSoon($kpi),
            'is_overdue' => $this->kpiStatusService->isOverdue($kpi),
            'next_due_date' => $kpi->getNextDueDate(),
        ];
    }

    /**
     * Vergleichsfunktion für die Sortierung nach Fälligkeitsdatum.
     */
    private function compareDueDate(array $a, array $b): int
    {
        if (null === $a['next_due_date'] && null === $b['next_due_date']) {
            return 0;
        }
        if (null === $a['next_due_date']) {
            return 1;
        }
        if (null === $b['next_due_date']) {
            return -1;
        }

        return $a['next_due_date'] <=> $b['next_due_date'];
    }
}
