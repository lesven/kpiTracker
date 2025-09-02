<?php

namespace App\Domain\Service;

use App\Domain\ValueObject\KPIStatus;
use App\Domain\ValueObject\KpiInterval;
use App\Entity\KPI;
use App\Repository\KPIValueRepository;

/**
 * Domain Service für komplexe KPI-Status-Berechnungen.
 *
 * Kapselt die Business-Logic zur Bestimmung des KPI-Status basierend auf
 * Fälligkeitsdaten, Zeiträumen und konfigurierbaren Schwellwerten.
 *
 * Im Gegensatz zu einfachen Entity-Methoden kann dieser Service:
 * - Komplexe Multi-KPI Szenarien behandeln
 * - Dynamische Schwellwerte verwenden
 * - Externe Abhängigkeiten (Repository) nutzen
 * - Erweiterte Status-Logiken implementieren
 */
class KPIStatusDomainService
{
    /**
     * Standard-Schwellwerte für Status-Bestimmung (in Tagen).
     */
    private const DEFAULT_WARNING_DAYS = 3;
    private const DEFAULT_CRITICAL_DAYS = 0;

    public function __construct(
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Berechnet den Status einer KPI mit konfigurierbaren Schwellwerten.
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @param int $warningDays Tage vor Fälligkeit für Warnung (gelb)
     * @param int $criticalDays Tage nach Fälligkeit für kritischen Status (rot)
     * @return KPIStatus Der berechnete Status
     */
    public function calculateStatus(
        KPI $kpi,
        int $warningDays = self::DEFAULT_WARNING_DAYS,
        int $criticalDays = self::DEFAULT_CRITICAL_DAYS
    ): KPIStatus {
        $currentPeriod = $kpi->getCurrentPeriod();
        
        // Prüfen ob bereits Wert für aktuellen Zeitraum erfasst
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);
        
        if ($existingValue) {
            return KPIStatus::green();
        }

        // Fälligkeitsdatum berechnen
        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();
        
        $daysDifference = $this->calculateDaysDifference($now, $dueDate);

        return $this->determineStatusByDaysDifference($daysDifference, $warningDays, $criticalDays);
    }

    /**
     * Berechnet Status basierend auf Anzahl KPIs in verschiedenen Status-Kategorien.
     * Nützlich für Dashboard-Übersichten und Team-Status.
     *
     * @param array $kpis Liste von KPIs
     * @return array Status-Zusammenfassung
     */
    public function calculateAggregatedStatus(array $kpis): array
    {
        $statusCounts = [
            KPIStatus::GREEN => 0,
            KPIStatus::YELLOW => 0,
            KPIStatus::RED => 0,
        ];

        $totalKpis = count($kpis);
        
        foreach ($kpis as $kpi) {
            $status = $this->calculateStatus($kpi);
            $statusCounts[$status->toString()]++;
        }

        $overallStatus = $this->determineOverallStatus($statusCounts);

        return [
            'overall_status' => $overallStatus,
            'total_kpis' => $totalKpis,
            'green_count' => $statusCounts[KPIStatus::GREEN],
            'yellow_count' => $statusCounts[KPIStatus::YELLOW],
            'red_count' => $statusCounts[KPIStatus::RED],
            'green_percentage' => $totalKpis > 0 ? round(($statusCounts[KPIStatus::GREEN] / $totalKpis) * 100, 1) : 0,
            'critical_percentage' => $totalKpis > 0 ? round((($statusCounts[KPIStatus::YELLOW] + $statusCounts[KPIStatus::RED]) / $totalKpis) * 100, 1) : 0,
        ];
    }

    /**
     * Prüft ob eine KPI in einem kritischen Zustand ist.
     * Erweiterte Logik für besondere Geschäftsregeln.
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @param array $businessRules Zusätzliche Geschäftsregeln
     * @return bool true wenn KPI kritisch ist
     */
    public function isCritical(KPI $kpi, array $businessRules = []): bool
    {
        $status = $this->calculateStatus($kpi);
        
        if ($status->isRed()) {
            return true;
        }

        // Erweiterte Geschäftsregeln prüfen
        if ($this->hasHighBusinessImpact($kpi, $businessRules)) {
            return $status->isYellow(); // Gelbe High-Impact KPIs sind auch kritisch
        }

        return false;
    }

    /**
     * Berechnet die Eskalationsstufe einer überfälligen KPI.
     *
     * @param KPI $kpi Die überfällige KPI
     * @return int Eskalationsstufe (0 = nicht überfällig, 1-3 = Stufen)
     */
    public function calculateEscalationLevel(KPI $kpi): int
    {
        $status = $this->calculateStatus($kpi);
        
        if (!$status->isRed()) {
            return 0; // Nicht überfällig
        }

        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();
        $daysOverdue = $this->calculateDaysDifference($now, $dueDate);

        return match (true) {
            $daysOverdue <= 3 => 1,      // Leicht überfällig
            $daysOverdue <= 7 => 2,      // Moderat überfällig  
            $daysOverdue <= 14 => 3,     // Stark überfällig
            default => 4,                // Kritisch überfällig
        };
    }

    /**
     * Gibt alle KPIs zurück, die eine bestimmte Status-Bedingung erfüllen.
     *
     * @param array $kpis Liste von KPIs
     * @param callable $statusCondition Bedingung als Callback (KPIStatus $status): bool
     * @return array Gefilterte KPIs
     */
    public function filterByStatusCondition(array $kpis, callable $statusCondition): array
    {
        return array_filter($kpis, function (KPI $kpi) use ($statusCondition) {
            $status = $this->calculateStatus($kpi);
            return $statusCondition($status);
        });
    }

    /**
     * Berechnet das Fälligkeitsdatum basierend auf KPI-Intervall.
     * Erweiterte Logik mit Geschäftstag-Berücksichtigung.
     */
    private function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();

        $dueDate = match ($kpi->getInterval()) {
            KpiInterval::WEEKLY => $this->getNextMonday($now),
            KpiInterval::MONTHLY => $this->getFirstOfNextMonth($now),
            KpiInterval::QUARTERLY => $this->getNextQuarterStart($now),
            default => $now->modify('+1 week'),
        };

        // Optional: Auf nächsten Geschäftstag verschieben wenn Wochenende
        return $this->adjustToBusinessDay($dueDate);
    }

    /**
     * Berechnet Tage-Differenz zwischen zwei Daten.
     * Positiv = zukunft, Negativ = vergangenheit.
     */
    private function calculateDaysDifference(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $interval = $from->diff($to);
        $days = (int) $interval->days;
        
        return $from > $to ? -$days : $days;
    }

    /**
     * Bestimmt Status basierend auf Tage-Differenz zu Fälligkeitsdatum.
     */
    private function determineStatusByDaysDifference(int $daysDifference, int $warningDays, int $criticalDays): KPIStatus
    {
        if ($daysDifference < $criticalDays) {
            return KPIStatus::red(); // Überfällig
        }

        if ($daysDifference <= $warningDays) {
            return KPIStatus::yellow(); // Bald fällig
        }

        return KPIStatus::green(); // Noch Zeit
    }

    /**
     * Bestimmt Overall-Status basierend auf Status-Verteilung.
     */
    private function determineOverallStatus(array $statusCounts): KPIStatus
    {
        $total = array_sum($statusCounts);
        
        if ($total === 0) {
            return KPIStatus::green();
        }

        $redPercentage = ($statusCounts[KPIStatus::RED] / $total) * 100;
        $yellowPercentage = ($statusCounts[KPIStatus::YELLOW] / $total) * 100;

        if ($redPercentage > 20) { // Mehr als 20% rot
            return KPIStatus::red();
        }

        if ($redPercentage > 0 || $yellowPercentage > 30) { // Beliebige rote oder >30% gelbe
            return KPIStatus::yellow();
        }

        return KPIStatus::green();
    }

    /**
     * Prüft ob KPI hohe Geschäftsauswirkung hat.
     */
    private function hasHighBusinessImpact(KPI $kpi, array $businessRules): bool
    {
        // Beispiel-Implementierung für Geschäftsregeln
        $highImpactKeywords = ['Umsatz', 'Revenue', 'Critical', 'Kritisch'];
        
        foreach ($highImpactKeywords as $keyword) {
            if (str_contains($kpi->getName(), $keyword)) {
                return true;
            }
        }

        // Weitere Business Rules aus $businessRules array prüfen
        return isset($businessRules['high_impact_kpis']) 
            && in_array($kpi->getId(), $businessRules['high_impact_kpis'], true);
    }

    /**
     * Ermittelt nächsten Montag.
     */
    private function getNextMonday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('N');

        if (1 === $dayOfWeek) {
            return $date->modify('+1 week');
        }

        $daysUntilMonday = 8 - $dayOfWeek;
        return $date->modify("+{$daysUntilMonday} days");
    }

    /**
     * Ermittelt ersten Tag des nächsten Monats.
     */
    private function getFirstOfNextMonth(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('first day of next month');
    }

    /**
     * Ermittelt nächsten Quartalsbeginn.
     */
    private function getNextQuarterStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $currentMonth = (int) $date->format('n');

        $nextQuarterMonth = match (true) {
            $currentMonth <= 3 => 4,
            $currentMonth <= 6 => 7,
            $currentMonth <= 9 => 10,
            default => 1,
        };

        $year = 1 === $nextQuarterMonth ? (int) $date->format('Y') + 1 : (int) $date->format('Y');

        return new \DateTimeImmutable("{$year}-{$nextQuarterMonth}-01");
    }

    /**
     * Verschiebt Datum auf nächsten Geschäftstag falls Wochenende.
     */
    private function adjustToBusinessDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('N'); // 1=Montag, 7=Sonntag

        if ($dayOfWeek === 6) { // Samstag → Montag
            return $date->modify('+2 days');
        }

        if ($dayOfWeek === 7) { // Sonntag → Montag
            return $date->modify('+1 day');
        }

        return $date; // Bereits Geschäftstag
    }
}