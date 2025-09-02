<?php

namespace App\Service;

use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service-Klasse für das Erstellen und Bearbeiten von KPI-Werten.
 *
 * Bietet Methoden zur Speicherung und zum Upload von Dateien zu KPI-Werten.
 */
class KPIValueService
{
    public function __construct(
        private KPIAggregate $kpiAggregate,
    ) {
    }

    public function addValue(KPIValue $kpiValue, ?array $uploadedFiles = null): array
    {
        return $this->kpiAggregate->addValue($kpiValue, $uploadedFiles);
    }
}
