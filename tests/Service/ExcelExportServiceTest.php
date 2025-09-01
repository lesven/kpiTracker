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

    public function testCreateKpiExportResponseSetsCorrectHeaders(): void
    {
        $service = new ExcelExportService();
        $response = $service->createKpiExportResponse([]);
        
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="kpi_export_', $response->headers->get('Content-Disposition'));
    }

    public function testCreateKpiExportResponseHandlesEmptyData(): void
    {
        $service = new ExcelExportService();
        $response = $service->createKpiExportResponse([]);
        
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
