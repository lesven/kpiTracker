<?php

namespace App\Factory;

use App\Entity\KPI;
use App\Entity\User;
use App\Domain\ValueObject\KpiInterval;

class KPIFactory
{
    public function createForUser(User $user): KPI
    {
        $kpi = new KPI();
        $kpi->setUser($user);
        // Set default interval if needed
        $kpi->setInterval(KpiInterval::MONTHLY);

        return $kpi;
    }
}
