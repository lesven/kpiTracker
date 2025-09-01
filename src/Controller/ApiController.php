<?php

namespace App\Controller;

use App\Entity\KPI;
use App\Repository\KPIValueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiController extends AbstractController
{
    /**
     * Gibt alle Werte einer KPI als JSON zurück (API-Endpunkt).
     *
     * @param KPI                $kpi        Die zugehörige KPI-Entität
     * @param KPIValueRepository $repository Repository für KPI-Werte
     *
     * @return JsonResponse JSON-Array mit KPI-Werten
     */
    #[Route('/kpi/{id}/values', name: 'api_kpi_values', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function values(KPI $kpi, KPIValueRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $kpi);
        $values = $repository->findByKPI($kpi);

        $data = [];
        foreach ($values as $value) {
            $data[] = [
                'timestamp' => $value->getCreatedAt()->getTimestamp() * 1000,
                'value' => $value->getValueAsFloat(),
                'period' => $value->getPeriod()?->value(),
                'formatted_period' => $value->getFormattedPeriod(),
            ];
        }

        return $this->json([
            'kpi' => $kpi->getName(),
            'unit' => $kpi->getUnit(),
            'interval' => $kpi->getInterval()?->value,
            'interval_label' => $kpi->getInterval()?->label(),
            'data' => $data,
        ]);
    }
}
