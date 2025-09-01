<?php

namespace App\Tests\Service;

use App\Service\FileUploadService;
use App\Entity\KPIValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class FileUploadServiceTest extends TestCase
{
    public function testHandleFileUploadsReturnsStatsArray(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new FileUploadService($em, $slugger, $logger, 'uploads/');
        $kpiValue = $this->createMock(KPIValue::class);
        $result = $service->handleFileUploads(null, $kpiValue);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('uploaded', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
    }
}
