<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service-Klasse für den Export von KPI-Daten als Excel-Datei.
 *
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
     * Erstellt Spreadsheet mit KPI-Daten.
     */
    public function createExcelWithKpis(array $kpis): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KPI Export');

        $this->addKpiListHeaders($sheet);
        $this->addKpiListDataRows($sheet, $kpis);

        return $spreadsheet;
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
     * Fügt Excel-Header für KPI-Listen-Export hinzu.
     */
    private function addKpiListHeaders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $headers = [
            'Name',
            'Beschreibung',
            'Intervall',
            'Ziel',
            'Einheit',
            'Benutzer',
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
     * Fügt KPI-Listen-Datenzeilen zu Excel-Sheet hinzu.
     */
    private function addKpiListDataRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $kpis): void
    {
        $currentRow = 2; // Start nach Header-Zeile

        foreach ($kpis as $kpi) {
            $rowData = $this->extractKpiListRowData($kpi);
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
            $user?->getEmail()?->getValue() ?? self::DEFAULT_VALUE,
            $kpi?->getName() ?? self::DEFAULT_VALUE,
            (string) $kpiValue->getValue(),
            (string) $kpiValue->getPeriod(),
            $kpi?->getUnit() ?? self::DEFAULT_VALUE,
        ];
    }

    /**
     * Extrahiert Zeilen-Daten aus KPI Entity.
     */
    private function extractKpiListRowData($kpi): array
    {
        $user = $kpi?->getUser();

        return [
            $kpi?->getName() ?? self::DEFAULT_VALUE,
            $kpi?->getDescription() ?? self::DEFAULT_VALUE,
            $kpi?->getInterval()?->value ?? self::DEFAULT_VALUE,
            $kpi?->getTarget() ? (string) $kpi->getTarget() : self::DEFAULT_VALUE,
            $kpi?->getUnit() ?? self::DEFAULT_VALUE,
            $user ? sprintf('%s %s (%s)', $user->getFirstName(), $user->getLastName(), $user->getEmail()->getValue()) : self::DEFAULT_VALUE,
        ];
    }

    /**
     * Schreibt eine Datenzeile in das Excel-Sheet.
     */
    private function writeRowToSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $rowData, int $rowNumber): void
    {
        $columns = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($rowData as $index => $value) {
            if (isset($columns[$index])) {
                $sheet->setCellValue($columns[$index].$rowNumber, $value);
            }
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
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d_H-i-s');

        return sprintf('%s_%s.xlsx', self::EXPORT_FILENAME_PREFIX, $timestamp);
    }
}
