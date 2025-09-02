<?php

namespace App\Factory;

use App\Domain\ValueObject\KpiInterval;
use App\Entity\KPI;
use App\Entity\User;

/**
 * Factory für die Erstellung von KPI-Entitäten.
 */
class KPIFactory
{
    /**
     * Erstellt eine neue KPI-Entität für einen Benutzer mit Standard-Intervall.
     *
     * @param User $user Der Benutzer, dem die KPI zugeordnet wird
     *
     * @return KPI Die neu erstellte KPI-Entität
     */
    public function createForUser(User $user): KPI
    {
        $kpi = new KPI();
        $kpi->setUser($user);
        // Setzt das Standardintervall auf monatlich
        $kpi->setInterval(KpiInterval::MONTHLY);

        return $kpi;
    }
}
