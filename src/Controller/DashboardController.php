<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Service\KPIStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard-Controller für die Hauptübersicht
 * User Story 9: KPI-Dashboard mit Ampellogik.
 */
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private KPIRepository $kpiRepository,
        private KPIValueRepository $kpiValueRepository,
        private KPIStatusService $kpiStatusService,
    ) {
    }

    /**
     * Hauptdashboard mit KPI-Übersicht und Ampellogik.
     */
    #[Route('/', name: 'app_dashboard')]
    #[Route('/dashboard', name: 'app_dashboard_alt')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Alle KPIs des Benutzers mit Status laden
        $userKpis = $this->kpiRepository->findByUser($user);
        $kpiData = [];

        foreach ($userKpis as $kpi) {
            $status = $this->kpiStatusService->getKpiStatus($kpi);
            $latestValue = $this->kpiValueRepository->findByKPI($kpi)[0] ?? null;

            $kpiData[] = [
                'kpi' => $kpi,
                'status' => $status,
                'latest_value' => $latestValue,
                'is_due_soon' => $this->kpiStatusService->isDueSoon($kpi),
                'is_overdue' => $this->kpiStatusService->isOverdue($kpi),
                'next_due_date' => $kpi->getNextDueDate(),
            ];
        }

        // Nach Fälligkeitsdatum sortieren (nächstes Fälligkeitsdatum zuerst)
        usort($kpiData, static function (array $a, array $b): int {
            if ($a['next_due_date'] === null && $b['next_due_date'] === null) {
                return 0;
            }
            if ($a['next_due_date'] === null) {
                return 1;
            }
            if ($b['next_due_date'] === null) {
                return -1;
            }
            return $a['next_due_date'] <=> $b['next_due_date'];
        });

        // Statistiken für Dashboard
        $stats = [
            'total_kpis' => count($userKpis),
            'overdue_count' => count(array_filter($kpiData, fn ($item) => 'red' === $item['status'])),
            'due_soon_count' => count(array_filter($kpiData, fn ($item) => 'yellow' === $item['status'])),
            'up_to_date_count' => count(array_filter($kpiData, fn ($item) => 'green' === $item['status'])),
            'recent_values' => $this->kpiValueRepository->findRecentByUser($user, 5),
        ];

        return $this->render('dashboard/index.html.twig', [
            'kpi_data' => $kpiData,
            'stats' => $stats,
            'user' => $user,
        ]);
    }

    /**
     * AJAX-Endpunkt für Dashboard-Updates (für Live-Updates).
     */
    #[Route('/dashboard/refresh', name: 'app_dashboard_refresh', methods: ['GET'])]
    public function refresh(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $userKpis = $this->kpiRepository->findByUser($user);
        $statusSummary = [
            'green' => 0,
            'yellow' => 0,
            'red' => 0,
        ];

        foreach ($userKpis as $kpi) {
            $status = $this->kpiStatusService->getKpiStatus($kpi);
            ++$statusSummary[$status];
        }

        return $this->json([
            'success' => true,
            'summary' => $statusSummary,
            'last_updated' => (new \DateTimeImmutable())->format('H:i:s'),
        ]);
    }
}
