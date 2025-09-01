<?php

namespace App\Tests\Service;

use App\Entity\KPIValue;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

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

    public function testValidateFileReturnsTrueForValidFile(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new FileUploadService($em, $slugger, $logger, 'uploads/');

        $file = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(1024 * 1024); // 1MB
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getClientOriginalExtension')->willReturn('pdf');

        $result = $service->validateFile($file);
        $this->assertIsArray($result);
        $this->assertEmpty($result); // Keine Fehler bei gültiger Datei
    }

    public function testValidateFileReturnsErrorsForInvalidFile(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new FileUploadService($em, $slugger, $logger, 'uploads/');

        $file = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(10 * 1024 * 1024); // 10MB - zu groß
        $file->method('getMimeType')->willReturn('application/pdf');

        $result = $service->validateFile($file);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result); // Sollte Fehler enthalten
    }
}
