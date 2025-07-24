<?php

namespace App\Service;

use App\Entity\KPIValue;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\KPIValueRepository;

/**
 * Service für das Erstellen und Bearbeiten von KPI-Werten.
 */
class KPIValueService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KPIValueRepository $kpiValueRepository,
        private FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Speichert einen neuen KPI-Wert und verarbeitet optionale Datei-Uploads.
     *
     * @param KPIValue    $kpiValue      zu speichernder Wert
     * @param array|null  $uploadedFiles hochgeladene Dateien
     *
     * @return array Ergebnisdaten und Upload-Statistiken
     */
    public function addValue(KPIValue $kpiValue, ?array $uploadedFiles = null): array
    {
        $existing = $this->kpiValueRepository->findByKpiAndPeriod(
            $kpiValue->getKpi(),
            $kpiValue->getPeriod(),
        );

        if (null !== $existing) {
            return ['status' => 'duplicate', 'existing' => $existing];
        }

        $this->entityManager->persist($kpiValue);
        $this->entityManager->flush();

        $uploadStats = [];
        if ($uploadedFiles) {
            $uploadStats = $this->fileUploadService->handleFileUploads($uploadedFiles, $kpiValue);
            $this->entityManager->flush();
        }

        return ['status' => 'success', 'upload' => $uploadStats];
    }
}

