<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardService;
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
    /**
     * Konstruktor injiziert den DashboardService.
     */
    public function __construct(
        private DashboardService $dashboardService,
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

        $kpiData = $this->dashboardService->getKpiDataForUser($user);
        $stats = $this->dashboardService->getDashboardStats($user, $kpiData);

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

        $statusSummary = $this->dashboardService->getStatusSummaryForUser($user);

        return $this->json([
            'success' => true,
            'summary' => $statusSummary,
            'last_updated' => (new \DateTimeImmutable())->format('H:i:s'),
        ]);
    }
}
