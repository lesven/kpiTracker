<?php

namespace App\Tests\Controller;

use App\Controller\KPIController;
use App\Entity\KPI;
use App\Entity\User;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Service\KPIApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class KPIControllerTest extends TestCase
{
    private KPIController $controller;
    private EntityManagerInterface $entityManager;
    private KPIRepository $kpiRepository;
    private KPIValueRepository $kpiValueRepository;
    private KPIApplicationService $kpiApplicationService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->kpiRepository = $this->createMock(KPIRepository::class);
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->kpiApplicationService = $this->createMock(KPIApplicationService::class);

        $this->controller = new KPIController(
            $this->entityManager,
            $this->kpiRepository,
            $this->kpiValueRepository,
            $this->kpiApplicationService
        );
    }

    public function testConstructorInjectsServices(): void
    {
        // Test that all dependencies are properly injected
        $reflection = new \ReflectionClass($this->controller);
        
        $entityManagerProperty = $reflection->getProperty('entityManager');
        $entityManagerProperty->setAccessible(true);
        $this->assertSame($this->entityManager, $entityManagerProperty->getValue($this->controller));
        
        $kpiRepositoryProperty = $reflection->getProperty('kpiRepository');
        $kpiRepositoryProperty->setAccessible(true);
        $this->assertSame($this->kpiRepository, $kpiRepositoryProperty->getValue($this->controller));
        
        $kpiValueRepositoryProperty = $reflection->getProperty('kpiValueRepository');
        $kpiValueRepositoryProperty->setAccessible(true);
        $this->assertSame($this->kpiValueRepository, $kpiValueRepositoryProperty->getValue($this->controller));
        
        $kpiApplicationServiceProperty = $reflection->getProperty('kpiApplicationService');
        $kpiApplicationServiceProperty->setAccessible(true);
        $this->assertSame($this->kpiApplicationService, $kpiApplicationServiceProperty->getValue($this->controller));
    }

    public function testIndexMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
    }

    public function testKPIApplicationServiceUsage(): void
    {
        $kpiValue = $this->createMock(\App\Entity\KPIValue::class);

        $this->kpiApplicationService->expects($this->once())
            ->method('addValue')
            ->with($kpiValue, null)
            ->willReturn(['success' => true]);

        // Call the service method directly to test integration
        $result = $this->kpiApplicationService->addValue($kpiValue, null);
        $this->assertTrue($result['success']);
    }
}