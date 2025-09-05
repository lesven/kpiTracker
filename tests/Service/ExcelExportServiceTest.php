<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Entity\User;
use App\Service\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Domain\ValueObject\EmailAddress;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\DecimalValue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportServiceTest extends TestCase
{
    private ExcelExportService $service;

    protected function setUp(): void
    {
        $this->service = new ExcelExportService();
    }

    public function testCreateKpiExportResponseReturnsStreamedResponse(): void
    {
        $response = $this->service->createKpiExportResponse([]);
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testCreateKpiExportResponseSetsCorrectHeaders(): void
    {
        $response = $this->service->createKpiExportResponse([]);

        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="kpi_export_', $response->headers->get('Content-Disposition'));
    }

    public function testCreateKpiExportResponseHandlesEmptyData(): void
    {
        $response = $this->service->createKpiExportResponse([]);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateExcelWithKpisReturnsSpreadsheet(): void
    {
        $user = new User();
        $user->setEmail(new EmailAddress('test@example.com'))
            ->setFirstName('Test')
            ->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI')
            ->setDescription('Test Description')
            ->setInterval(KpiInterval::MONTHLY)
            ->setUser($user)
            ->setTarget(new DecimalValue('100.0'))
            ->setUnit('pieces');

        $kpis = [$kpi];

        $spreadsheet = $this->service->createExcelWithKpis($kpis);

        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $this->assertSame('KPI Export', $spreadsheet->getActiveSheet()->getTitle());
    }

    public function testCreateExcelWithKpisHasCorrectHeaders(): void
    {
        $user = new User();
        $user->setEmail(new EmailAddress('test@example.com'))
            ->setFirstName('Test')
            ->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI')
            ->setDescription('Test Description')
            ->setInterval(KpiInterval::MONTHLY)
            ->setUser($user)
            ->setTarget(new DecimalValue('100.0'))
            ->setUnit('pieces');

        $kpis = [$kpi];

        $spreadsheet = $this->service->createExcelWithKpis($kpis);
        $worksheet = $spreadsheet->getActiveSheet();

        // Prüfe Header
        $this->assertSame('Name', $worksheet->getCell('A1')->getValue());
        $this->assertSame('Beschreibung', $worksheet->getCell('B1')->getValue());
        $this->assertSame('Intervall', $worksheet->getCell('C1')->getValue());
        $this->assertSame('Ziel', $worksheet->getCell('D1')->getValue());
        $this->assertSame('Einheit', $worksheet->getCell('E1')->getValue());
        $this->assertSame('Benutzer', $worksheet->getCell('F1')->getValue());
    }

    public function testCreateExcelWithKpisPopulatesDataCorrectly(): void
    {
        $user = new User();
        $user->setEmail(new EmailAddress('test@example.com'))
            ->setFirstName('Test')
            ->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI')
            ->setDescription('Test Description')
            ->setInterval(KpiInterval::MONTHLY)
            ->setUser($user)
            ->setTarget(new DecimalValue('100.0'))
            ->setUnit('pieces');

        $kpis = [$kpi];

        $spreadsheet = $this->service->createExcelWithKpis($kpis);
        $worksheet = $spreadsheet->getActiveSheet();

        // Prüfe Daten in zweiter Zeile
        $this->assertSame('Test KPI', $worksheet->getCell('A2')->getValue());
        $this->assertSame('Test Description', $worksheet->getCell('B2')->getValue());
        $this->assertSame('monthly', $worksheet->getCell('C2')->getValue());
        $this->assertSame('100,00', $worksheet->getCell('D2')->getValue()); // DecimalValue formatiert mit Komma
        $this->assertSame('pieces', $worksheet->getCell('E2')->getValue());
        $this->assertSame('Test User (test@example.com)', $worksheet->getCell('F2')->getValue());
    }

    public function testCreateExcelWithMultipleKpisPopulatesAllRows(): void
    {
        $user1 = new User();
        $user1->setEmail(new EmailAddress('user1@example.com'))
            ->setFirstName('User')
            ->setLastName('One');

        $user2 = new User();
        $user2->setEmail(new EmailAddress('user2@example.com'))
            ->setFirstName('User')
            ->setLastName('Two');

        $kpi1 = new KPI();
        $kpi1->setName('KPI 1')
            ->setDescription('Description 1')
            ->setInterval(KpiInterval::WEEKLY)
            ->setUser($user1);

        $kpi2 = new KPI();
        $kpi2->setName('KPI 2')
            ->setDescription('Description 2')
            ->setInterval(KpiInterval::MONTHLY)
            ->setUser($user2);

        $kpis = [$kpi1, $kpi2];

        $spreadsheet = $this->service->createExcelWithKpis($kpis);
        $worksheet = $spreadsheet->getActiveSheet();

        // Prüfe beide Zeilen
        $this->assertSame('KPI 1', $worksheet->getCell('A2')->getValue());
        $this->assertSame('KPI 2', $worksheet->getCell('A3')->getValue());
        $this->assertSame('weekly', $worksheet->getCell('C2')->getValue());
        $this->assertSame('monthly', $worksheet->getCell('C3')->getValue());
        $this->assertSame('User One (user1@example.com)', $worksheet->getCell('F2')->getValue());
        $this->assertSame('User Two (user2@example.com)', $worksheet->getCell('F3')->getValue());
    }

    public function testCreateExcelWithKpisHandlesNullValues(): void
    {
        $user = new User();
        $user->setEmail(new EmailAddress('test@example.com'))
            ->setFirstName('Test')
            ->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI')
            ->setDescription('Test Description')
            ->setInterval(KpiInterval::MONTHLY)
            ->setUser($user);
        // Target und Unit nicht gesetzt (null)

        $kpis = [$kpi];

        $spreadsheet = $this->service->createExcelWithKpis($kpis);
        $worksheet = $spreadsheet->getActiveSheet();

        // Prüfe dass null-Werte korrekt behandelt werden
        $this->assertSame('Test KPI', $worksheet->getCell('A2')->getValue());
        $this->assertSame('N/A', $worksheet->getCell('D2')->getValue()); // Target als N/A wenn null
        $this->assertSame('N/A', $worksheet->getCell('E2')->getValue()); // Unit als N/A wenn null
    }
}
