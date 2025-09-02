<?php

namespace App\Service;

use App\DTO\DashboardKpiEntry;
use App\Entity\KPI;
use App\Entity\User;
use App\Factory\DashboardKpiEntryFactory;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;

/**
 * Service-Klasse zur Aufbereitung und Berechnung von Dashboard-Daten.
 *
 * Liefert KPI-Daten und Statistiken für Benutzer-Dashboards.
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
        private DashboardKpiEntryFactory $kpiEntryFactory,
    ) {
    }

    /**
     * Liefert die KPI-Daten für das Dashboard eines Benutzers.
     *
     * @return array<int, DashboardKpiEntry>
     */
    public function getKpiDataForUser(User $user): array
    {
        $kpis = $this->kpiRepository->findByUser($user);

        $data = array_map(
            fn (KPI $kpi): DashboardKpiEntry => $this->kpiEntryFactory->create($kpi),
            $kpis
        );

        usort($data, $this->compareDueDate(...));

        return $data;
    }

    /**
     * Erstellt Statistiken für das Dashboard.
     *
     * @param array<int, DashboardKpiEntry> $kpiData
     *
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
            'overdue_count' => count(array_filter($kpiData, fn (DashboardKpiEntry $item) => 'red' === $item->status)),
            'due_soon_count' => count(array_filter($kpiData, fn (DashboardKpiEntry $item) => 'yellow' === $item->status)),
            'up_to_date_count' => count(array_filter($kpiData, fn (DashboardKpiEntry $item) => 'green' === $item->status)),
            'recent_values' => $this->kpiValueRepository->findRecentByUser($user, 5),
        ];
    }

    /**
     * Zählt KPIs pro Status für Live-Updates.
     */
    /**
     * Provides a summary of KPI statuses for a given user.
     *
     * @param User $user the user whose KPIs are being summarized
     *
     * @return array<string, int> an associative array with keys 'green', 'yellow', and 'red',
     *                            representing the count of KPIs in each status
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
     * Vergleichsfunktion für die Sortierung nach Fälligkeitsdatum.
     *
     * @param DashboardKpiEntry $a first KPI entry
     * @param DashboardKpiEntry $b second KPI entry
     *
     * @return int comparison result: -1 if $a < $b, 1 if $a > $b, 0 if equal
     */
    private function compareDueDate(DashboardKpiEntry $a, DashboardKpiEntry $b): int
    {
        if (null === $a->nextDueDate && null === $b->nextDueDate) {
            return 0;
        }
        if (null === $a->nextDueDate) {
            return 1;
        }
        if (null === $b->nextDueDate) {
            return -1;
        }

        return $a->nextDueDate <=> $b->nextDueDate;
    }
}
