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
    #[Route('/kpi/{id}/values', name: 'api_kpi_values', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function values(KPI $kpi, KPIValueRepository $repository): JsonResponse
    {
        $values = $repository->findByKPI($kpi);

        $data = [];
        foreach ($values as $value) {
            $data[] = [
                'timestamp' => $value->getCreatedAt()->getTimestamp() * 1000,
                'value' => (float) $value->getValue(),
                'period' => $value->getPeriod(),
            ];
        }

        return $this->json([
            'kpi' => $kpi->getName(),
            'unit' => $kpi->getUnit(),
            'data' => $data,
        ]);
    }
}
