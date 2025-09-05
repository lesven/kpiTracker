<?php

namespace App\Tests\Service;

use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class FileUploadServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;
    private LoggerInterface $logger;
    private FileUploadService $fileUploadService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->fileUploadService = new FileUploadService(
            $this->entityManager,
            $this->slugger,
            $this->logger
        );
    }

    public function testValidateFileReturnsEmptyArrayForValidFile(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getSize')->willReturn(1024);
        $uploadedFile->method('getMimeType')->willReturn('application/pdf');
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getClientOriginalName')->willReturn('test.pdf');

        $result = $this->fileUploadService->validateFile($uploadedFile);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testValidateFileReturnsErrorsForTooLargeFile(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getSize')->willReturn(6 * 1024 * 1024); // 6MB
        $uploadedFile->method('getMimeType')->willReturn('application/pdf');
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getClientOriginalName')->willReturn('test.pdf');

        $result = $this->fileUploadService->validateFile($uploadedFile);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('zu groß', $result[0]);
    }

    public function testValidateFileReturnsErrorsForInvalidMimeType(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getSize')->willReturn(1024);
        $uploadedFile->method('getMimeType')->willReturn('application/x-executable');
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getClientOriginalName')->willReturn('test.exe');

        $result = $this->fileUploadService->validateFile($uploadedFile);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('nicht erlaubt', $result[0]);
    }

    public function testHandleFileUploadsReturnsStatsForValidFiles(): void
    {
        $kpiValue = $this->createMock(\App\Entity\KPIValue::class);
        $kpiValue->method('getId')->willReturn(1);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getClientOriginalName')->willReturn('test.pdf');
        $uploadedFile->method('getSize')->willReturn(1024);
        $uploadedFile->method('getMimeType')->willReturn('application/pdf');
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('isValid')->willReturn(true);
        $uploadedFile->method('guessExtension')->willReturn('pdf');
        
        // UnicodeString-Mock erstellen
        $unicodeString = new UnicodeString('test-file');
        $this->slugger->method('slug')->willReturn($unicodeString);

        // Mock für das File-Objekt das von move() zurückgegeben wird
        $movedFile = $this->createMock(File::class);
        $uploadedFile->method('move')->willReturn($movedFile);

        $result = $this->fileUploadService->handleFileUploads([$uploadedFile], $kpiValue);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uploaded', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
    }
}