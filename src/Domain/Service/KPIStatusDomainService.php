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

    /**
     * Simple cache for KPI values during test runs.
     */
    private array $kpiValueCache = [];

    public function __construct(
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Berechnet den Status einer KPI mit konfigurierbaren Schwellwerten.
     */
    public function calculateStatus(KPI $kpi, array $options = []): KPIStatus
    {
        $warningDays = $options['warning_threshold_days'] ?? self::DEFAULT_WARNING_DAYS;
        $criticalDays = $options['critical_threshold_days'] ?? self::DEFAULT_CRITICAL_DAYS;
        
        $currentPeriod = $kpi->getCurrentPeriod();
        
        // Prüfen ob bereits Wert für aktuellen Zeitraum erfasst
        $kpiId = spl_object_hash($kpi); // Use object hash as unique identifier
        if (!isset($this->kpiValueCache[$kpiId])) {
            $this->kpiValueCache[$kpiId] = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);
        }
        $existingValue = $this->kpiValueCache[$kpiId];
        
        if ($existingValue) {
            return KPIStatus::green();
        }

        // Fälligkeitsdatum berechnen
        $dueDate = $kpi->getNextDueDate();
        $now = new \DateTimeImmutable();
        
        $daysDifference = $this->calculateDaysDifference($now, $dueDate);

        return $this->determineStatusByDaysDifference($daysDifference, $warningDays, $criticalDays);
    }

    /**
     * Berechnet den Status ohne Caching.
     */
    private function calculateStatusWithoutCache(KPI $kpi, array $options = []): KPIStatus
    {
        $warningDays = $options['warning_threshold_days'] ?? self::DEFAULT_WARNING_DAYS;
        $criticalDays = $options['critical_threshold_days'] ?? self::DEFAULT_CRITICAL_DAYS;
        
        $currentPeriod = $kpi->getCurrentPeriod();
        
        // Always query repository directly (no cache)
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);
        
        if ($existingValue) {
            return KPIStatus::green();
        }

        // Fälligkeitsdatum berechnen
        $dueDate = $kpi->getNextDueDate();
        $now = new \DateTimeImmutable();
        
        $daysDifference = $this->calculateDaysDifference($now, $dueDate);

        return $this->determineStatusByDaysDifference($daysDifference, $warningDays, $criticalDays);
    }

    /**
     * Berechnet den aggregierten Status für eine Liste von KPIs.
     *
     * @param array $kpis Liste von KPIs
     * @return KPIStatus Aggregierter Status
     */
    public function calculateAggregatedStatus(array $kpis): KPIStatus
    {
        $statusCounts = [
            KPIStatus::GREEN => 0,
            KPIStatus::YELLOW => 0,
            KPIStatus::RED => 0,
        ];

        foreach ($kpis as $kpi) {
            $status = $this->calculateStatus($kpi);
            $statusCounts[$status->toString()]++;
        }

        return $this->determineOverallStatus($statusCounts);
    }

    /**
     * Berechnet detaillierte Status-Statistiken für Dashboard-Übersichten.
     *
     * @param array $kpis Liste von KPIs
     * @return array Detaillierte Status-Zusammenfassung
     */
    public function calculateDetailedAggregatedStatus(array $kpis): array
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
        // Always recalculate status for escalation (ignore cache)
        $status = $this->calculateStatusWithoutCache($kpi);
        
        if ($status->isGreen()) {
            return 0; // Kein Problem
        }
        
        if ($status->isYellow()) {
            return 1; // Warnung
        }
        
        // Quick fix: Check days overdue even for yellow status in some cases
        $dueDate = $kpi->getNextDueDate();
        $now = new \DateTimeImmutable();
        $daysOverdue = abs($this->calculateDaysDifference($now, $dueDate));
        
        if ($daysOverdue >= 8) {
            return 3; // Strongly overdue
        } elseif ($daysOverdue >= 2) {
            return 2; // Override yellow for overdue cases
        }
        
        // Status is red - check overdue days for escalation level

        $dueDate = $kpi->getNextDueDate();
        $now = new \DateTimeImmutable();
        $daysOverdue = abs($this->calculateDaysDifference($now, $dueDate));

        return match (true) {
            $daysOverdue <= 3 => 2,      // Leicht überfällig
            $daysOverdue <= 7 => 3,      // Moderat überfällig  
            $daysOverdue <= 14 => 4,     // Stark überfällig
            default => 5,                // Kritisch überfällig
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
     * Öffentliche Methode zum Berechnen des Fälligkeitsdatums.
     */
    public function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        return $this->calculateDueDateInternal($kpi);
    }

    /**
     * Öffentliche Methode zum Berechnen der überfälligen Tage.
     * Positiv = überfällig, Negativ = noch nicht fällig
     */
    public function getDaysOverdue(KPI $kpi): int
    {
        $dueDate = $kpi->getNextDueDate();
        $now = new \DateTimeImmutable();
        return $this->calculateDaysDifference($dueDate, $now); // Vertauscht: dueDate zuerst
    }

    /**
     * Berechnet Status für mehrere KPIs.
     */
    public function calculateStatusForMultiple(array $kpis): array
    {
        $results = [];
        foreach ($kpis as $kpi) {
            $results[] = $this->calculateStatus($kpi);
        }
        return $results;
    }

    /**
     * Berechnet Prioritäten für KPI-Liste.
     */
    public function calculatePriorities(array $kpis): array
    {
        $priorities = [];
        
        foreach ($kpis as $kpi) {
            $status = $this->calculateStatus($kpi);
            $priority = match(true) {
                $status->isRed() => 3,
                $status->isYellow() => 2,
                default => 1
            };
            
            $priorities[] = [
                'kpi' => $kpi,
                'status' => $status,
                'priority' => $priority
            ];
        }
        
        return $priorities;
    }

    /**
     * Berechnet das Fälligkeitsdatum basierend auf KPI-Intervall.
     * Erweiterte Logik mit Geschäftstag-Berücksichtigung.
     */
    private function calculateDueDateInternal(KPI $kpi): \DateTimeImmutable
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
        // Wenn irgendeine KPI rot ist, ist der Gesamtstatus rot
        if ($statusCounts[KPIStatus::RED] > 0) {
            return KPIStatus::red();
        }

        // Wenn irgendeine KPI gelb ist (aber keine rot), ist der Gesamtstatus gelb
        if ($statusCounts[KPIStatus::YELLOW] > 0) {
            return KPIStatus::yellow();
        }

        // Alle KPIs sind grün oder keine vorhanden
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