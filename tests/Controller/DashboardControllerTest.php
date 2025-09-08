<?php

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Entity\User;
use App\Service\DashboardService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;
    private DashboardService $dashboardService;

    protected function setUp(): void
    {
        $this->dashboardService = $this->createMock(DashboardService::class);
        $this->controller = new DashboardController($this->dashboardService);
    }

    public function testRefreshMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'refresh'));
    }

    public function testDashboardServiceInjection(): void
    {
        // Test that service is properly injected
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('dashboardService');
        $property->setAccessible(true);
        
        $this->assertSame($this->dashboardService, $property->getValue($this->controller));
    }
}