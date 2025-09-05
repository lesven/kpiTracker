<?php

namespace App\Tests\Controller;

use App\Controller\MetricsController;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class MetricsControllerTest extends TestCase
{
    private MetricsController $controller;

    protected function setUp(): void
    {
        $this->controller = new MetricsController();
    }

    public function testMetricsReturnsResponse(): void
    {
        $repository = $this->createMock(KPIValueRepository::class);
        $repository->method('findForAdminExport')->willReturn([]);

        $response = $this->controller->metrics($repository);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain; version=0.0.4', $response->headers->get('Content-Type'));
    }

}