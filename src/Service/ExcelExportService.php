<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service für Excel-Export von KPI-Daten.
 * User Story 3: Administrator kann KPI-Daten exportieren.
 */
class ExcelExportService
{
    private const EXCEL_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const EXPORT_FILENAME_PREFIX = 'kpi_export';
    private const DEFAULT_VALUE = 'N/A';

    /**
     * Erstellt Excel-Export Response für KPI-Daten.
     */
    public function createKpiExportResponse(array $kpiValues): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($kpiValues) {
            $this->generateKpiExcelFile($kpiValues);
        });

        $this->setExcelDownloadHeaders($response);

        return $response;
    }

    /**
     * Generiert Excel-Datei mit KPI-Daten.
     */
    private function generateKpiExcelFile(array $kpiValues): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->addKpiHeaders($sheet);
        $this->addKpiDataRows($sheet, $kpiValues);

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Fügt Excel-Header für KPI-Export hinzu.
     */
    private function addKpiHeaders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $headers = [
            'Benutzer-Email',
            'KPI Name',
            'Wert',
            'Periode',
            'Einheit',
        ];

        $sheet->fromArray($headers, null, 'A1');
    }

    /**
     * Fügt KPI-Datenzeilen zu Excel-Sheet hinzu.
     */
    private function addKpiDataRows($sheet, array $kpiValues): void
    {
        $currentRow = 2; // Start nach Header-Zeile

        foreach ($kpiValues as $kpiValue) {
            $rowData = $this->extractKpiRowData($kpiValue);
            $this->writeRowToSheet($sheet, $rowData, $currentRow);
            ++$currentRow;
        }
    }

    /**
     * Extrahiert Zeilen-Daten aus KPIValue Entity.
     */
    private function extractKpiRowData($kpiValue): array
    {
        $kpi = $kpiValue->getKpi();
        $user = $kpi?->getUser();

        return [
            $user?->getEmail() ?? self::DEFAULT_VALUE,
            $kpi?->getName() ?? self::DEFAULT_VALUE,
            $kpiValue->getValue(),
            $kpiValue->getPeriod(),
            $kpi?->getUnit() ?? self::DEFAULT_VALUE,
        ];
    }

    /**
     * Schreibt eine Datenzeile in das Excel-Sheet.
     */
    private function writeRowToSheet($sheet, array $rowData, int $rowNumber): void
    {
        $columns = ['A', 'B', 'C', 'D', 'E'];

        foreach ($rowData as $index => $value) {
            $sheet->setCellValue($columns[$index] . $rowNumber, $value);
        }
    }

    /**
     * Setzt HTTP-Headers für Excel-Download.
     */
    private function setExcelDownloadHeaders(StreamedResponse $response): void
    {
        $filename = $this->generateExportFilename();

        $response->headers->set('Content-Type', self::EXCEL_MIME_TYPE);
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="%s"', $filename)
        );
    }

    /**
     * Generiert eindeutigen Dateinamen für Export.
     */
    private function generateExportFilename(): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        
        return sprintf('%s_%s.xlsx', self::EXPORT_FILENAME_PREFIX, $timestamp);
    }
}
