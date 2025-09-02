<?php

namespace App\Tests\Service;

use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use App\Service\FileUploadService;
use App\Service\KPIValueService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class KPIValueServiceTest extends TestCase
{
    public function testAddValueReturnsArray(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(KPIValueRepository::class);
        $uploadService = $this->createMock(FileUploadService::class);
        $kpiValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);
        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn(new Period('2024-01'));
        $repo->method('findByKpiAndPeriod')->willReturn(null);
        $em->expects($this->once())->method('persist')->with($kpiValue);
        $em->expects($this->once())->method('flush');
        $service = new KPIValueService($em, $repo, $uploadService);
        $result = $service->addValue($kpiValue);
        $this->assertIsArray($result);
        $this->assertSame('success', $result['status']);
    }

    public function testUpdateValueWithExistingValue(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(KPIValueRepository::class);
        $uploadService = $this->createMock(FileUploadService::class);

        $kpiValue = $this->createMock(KPIValue::class);
        $existingValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);

        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn(new Period('2024-01'));
        $repo->method('findByKpiAndPeriod')->willReturn($existingValue);

        $service = new KPIValueService($em, $repo, $uploadService);
        $result = $service->addValue($kpiValue);

        $this->assertIsArray($result);
        $this->assertSame('duplicate', $result['status']);
        $this->assertArrayHasKey('existing', $result);
    }

    public function testAddValueHandlesFileUploads(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(KPIValueRepository::class);
        $uploadService = $this->createMock(FileUploadService::class);

        $kpiValue = $this->createMock(KPIValue::class);
        $kpi = $this->createMock(KPI::class);

        $kpiValue->method('getKpi')->willReturn($kpi);
        $kpiValue->method('getPeriod')->willReturn(new Period('2024-01'));
        $repo->method('findByKpiAndPeriod')->willReturn(null);

        $uploadService->expects($this->once())
            ->method('handleFileUploads')
            ->willReturn(['uploaded' => 1, 'failed' => 0, 'errors' => []]);

        $service = new KPIValueService($em, $repo, $uploadService);
        $result = $service->addValue($kpiValue, ['files']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('upload', $result);
    }
}
