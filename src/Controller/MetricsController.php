<?php

namespace App\Controller;

use App\Repository\KPIValueRepository;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/metrics')]
class MetricsController extends AbstractController
{
    /**
     * Liefert alle KPI-Werte als Prometheus-Metriken (nur für Admins).
     *
     * @param KPIValueRepository $repository Repository für KPI-Werte
     *
     * @return Response Text-Response mit Prometheus-Metriken
     */
    #[Route('', name: 'app_metrics', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function metrics(KPIValueRepository $repository): Response
    {
        $adapter = new InMemory();
        $registry = new CollectorRegistry($adapter);

        $gauge = $registry->registerGauge('kpi', 'value', 'KPI historical values', ['kpi_id', 'period', 'kpi_name']);

        $values = $repository->findForAdminExport();
        foreach ($values as $value) {
            $kpi = $value->getKpi();
            $gauge->set($value->getValueAsFloat(), [
                (string) $kpi->getId(),
                (string) $value->getPeriod(),
                $kpi->getName(),
            ]);
        }

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, Response::HTTP_OK, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
