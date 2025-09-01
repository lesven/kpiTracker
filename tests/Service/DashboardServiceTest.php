<?php

namespace App\Tests\Service;

use App\Service\DashboardService;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Service\KPIStatusService;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    public function testGetKpiDataForUserReturnsArray(): void
    {
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $user = $this->createMock(User::class);

        $kpiRepo->method('findByUser')->willReturn([]);
        $service = new DashboardService($kpiRepo, $kpiValueRepo, $statusService);
        $result = $service->getKpiDataForUser($user);
        $this->assertIsArray($result);
    }
}
