<?php

namespace App\Tests\Controller;

use App\Controller\AdminController;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\KPIValueRepository;
use App\Repository\UserRepository;
use App\Service\AdminService;
use App\Service\ExcelExportService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminControllerTest extends TestCase
{
    private AdminController $controller;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserService $userService;
    private KPIValueRepository $kpiValueRepository;
    private ExcelExportService $excelExportService;
    private AdminService $adminService;
    private UserFactory $userFactory;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userService = $this->createMock(UserService::class);
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->excelExportService = $this->createMock(ExcelExportService::class);
        $this->adminService = $this->createMock(AdminService::class);
        $this->userFactory = $this->createMock(UserFactory::class);

        $this->controller = new AdminController(
            $this->entityManager,
            $this->userRepository,
            $this->userService,
            $this->kpiValueRepository,
            $this->excelExportService,
            $this->adminService,
            $this->userFactory
        );
    }

    public function testDashboardCallsAdminService(): void
    {
        $stats = [
            'users_count' => 10,
            'kpis_count' => 50,
            'values_count' => 1000,
        ];

        $this->adminService->expects($this->once())
            ->method('getDashboardStats')
            ->willReturn($stats);

        // Mock Twig
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html>Dashboard</html>');

        // Mock container
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($twig);

        // Set container
        $reflection = new \ReflectionClass($this->controller);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($this->controller, $container);

        $response = $this->controller->dashboard();
        
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testUsersCallsUserRepository(): void
    {
        $users = [
            $this->createMock(User::class),
            $this->createMock(User::class),
        ];

        $this->userRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($users);

        // Mock Twig
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html>Users</html>');

        // Mock container
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($twig);

        // Set container
        $reflection = new \ReflectionClass($this->controller);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($this->controller, $container);

        $response = $this->controller->users();
        
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testExportKpisReturnsStreamedResponse(): void
    {
        $kpiValues = [
            $this->createMock(\App\Entity\KPIValue::class),
        ];

        $streamedResponse = $this->createMock(StreamedResponse::class);

        $this->kpiValueRepository->expects($this->once())
            ->method('findForAdminExport')
            ->willReturn($kpiValues);

        $this->excelExportService->expects($this->once())
            ->method('createKpiExportResponse')
            ->with($kpiValues)
            ->willReturn($streamedResponse);

        $response = $this->controller->exportKpis();

        $this->assertSame($streamedResponse, $response);
    }

    public function testGetKpisWithLastValuesCallsAdminService(): void
    {
        $kpis = [$this->createMock(\App\Entity\KPI::class)];
        $lastValues = ['kpi_1' => $this->createMock(\App\Entity\KPIValue::class)];
        $result = [$kpis, $lastValues];

        $this->adminService->expects($this->once())
            ->method('getKpisWithLastValues')
            ->willReturn($result);

        // Mock Twig
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html>KPIs</html>');

        // Mock container
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($twig);

        // Set container
        $reflection = new \ReflectionClass($this->controller);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($this->controller, $container);

        $response = $this->controller->kpis();
        
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testConstructorInjectsAllDependencies(): void
    {
        // Test that all dependencies are properly injected
        $reflection = new \ReflectionClass($this->controller);
        
        $properties = [
            'entityManager' => $this->entityManager,
            'userRepository' => $this->userRepository,
            'userService' => $this->userService,
            'kpiValueRepository' => $this->kpiValueRepository,
            'excelExportService' => $this->excelExportService,
            'adminService' => $this->adminService,
            'userFactory' => $this->userFactory,
        ];

        foreach ($properties as $propertyName => $expectedValue) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $this->assertSame($expectedValue, $property->getValue($this->controller));
        }
    }
}