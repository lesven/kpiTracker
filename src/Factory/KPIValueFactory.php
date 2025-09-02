<?php

namespace App\Factory;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Domain\ValueObject\Period;


/**
 * Factory zur Erstellung von KPIValue-Entitäten.
 */
class KPIValueFactory
{
    /**
     * Erstellt eine neue KPIValue-Entität für ein KPI und einen Zeitraum.
     *
     * @param KPI $kpi Das zugehörige KPI-Objekt
     * @param Period|null $period Der Zeitraum, optional. Falls nicht gesetzt, wird das aktuelle Period des KPI verwendet
     * @return KPIValue Die neu erstellte KPIValue-Entität
     */
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
