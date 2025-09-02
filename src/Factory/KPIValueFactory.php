<?php

namespace App\Factory;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Domain\ValueObject\Period;

class KPIValueFactory
{
    public function create(KPI $kpi, ?Period $period = null): KPIValue
    {
        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setPeriod($period ?? $kpi->getCurrentPeriod());
        $kpiValue->setCreatedAt(new \DateTimeImmutable());
        $kpiValue->setUpdatedAt(null);

        return $kpiValue;
    }
}
