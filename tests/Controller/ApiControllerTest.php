<?php

namespace App\Tests\Controller;

use App\Controller\ApiController;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ApiControllerTest extends TestCase
{
    private ApiController $controller;
    private AuthorizationCheckerInterface $authorizationChecker;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->controller = new ApiController();

        // Set container mock
        $this->setProtectedProperty('container', $this->createContainerMock());
    }

    public function testValuesReturnsJsonResponse(): void
    {
        $kpi = $this->createMock(KPI::class);
        $repository = $this->createMock(KPIValueRepository::class);
        
        $kpi->method('getName')->willReturn('Test KPI');
        $kpi->method('getUnit')->willReturn('units');
        $kpi->method('getTargetAsFloat')->willReturn(100.0);
        $kpi->method('getTarget')->willReturn(null);
        $kpi->method('getInterval')->willReturn(null);

        $this->authorizationChecker->method('isGranted')
            ->with('view', $kpi)
            ->willReturn(true);

        $repository->method('findByKPI')
            ->with($kpi)
            ->willReturn([]);

        $response = $this->controller->values($kpi, $repository);

        $this->assertInstanceOf(JsonResponse::class, $response);
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test KPI', $data['kpi']);
        $this->assertEquals('units', $data['unit']);
        $this->assertEquals(100.0, $data['target']);
        $this->assertEmpty($data['data']);
    }

    private function setProtectedProperty(string $name, $value): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($this->controller, $value);
    }

    private function createContainerMock()
    {
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        
        $container->method('has')->willReturnCallback(function($id) {
            return in_array($id, ['security.authorization_checker']);
        });

        $container->method('get')->willReturnCallback(function($id) {
            switch($id) {
                case 'security.authorization_checker':
                    return $this->authorizationChecker;
                default:
                    throw new \Exception("Service not found: $id");
            }
        });

        return $container;
    }
}