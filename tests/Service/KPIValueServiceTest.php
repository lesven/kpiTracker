<?php

namespace App\Tests\Service;

use App\Entity\KPIValue;
use App\Service\KPIAggregate;
use App\Service\KPIValueService;
use PHPUnit\Framework\TestCase;

class KPIValueServiceTest extends TestCase
{
    public function testAddValueDelegatesToAggregate(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedResult = ['status' => 'success'];

        $aggregate->expects($this->once())
            ->method('addValue')
            ->with($kpiValue, null)
            ->willReturn($expectedResult);

        $service = new KPIValueService($aggregate);
        $result = $service->addValue($kpiValue);

        $this->assertSame($expectedResult, $result);
    }

    public function testAddValueWithFilesDelegatesToAggregate(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $uploadedFiles = ['file1', 'file2'];
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedResult = ['status' => 'success', 'upload' => ['uploaded' => 2]];

        $aggregate->expects($this->once())
            ->method('addValue')
            ->with($kpiValue, $uploadedFiles)
            ->willReturn($expectedResult);

        $service = new KPIValueService($aggregate);
        $result = $service->addValue($kpiValue, $uploadedFiles);

        $this->assertSame($expectedResult, $result);
    }

    public function testAddValueReturnsDuplicateFromAggregate(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $existingValue = $this->createMock(KPIValue::class);
        $aggregate = $this->createMock(KPIAggregate::class);
        $expectedResult = ['status' => 'duplicate', 'existing' => $existingValue];

        $aggregate->expects($this->once())
            ->method('addValue')
            ->with($kpiValue, null)
            ->willReturn($expectedResult);

        $service = new KPIValueService($aggregate);
        $result = $service->addValue($kpiValue);

        $this->assertSame($expectedResult, $result);
    }
}
