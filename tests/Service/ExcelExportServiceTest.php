<?php

namespace App\Tests\Service;

use App\Service\ExcelExportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportServiceTest extends TestCase
{
    public function testCreateKpiExportResponseReturnsStreamedResponse(): void
    {
        $service = new ExcelExportService();
        $response = $service->createKpiExportResponse([]);
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
