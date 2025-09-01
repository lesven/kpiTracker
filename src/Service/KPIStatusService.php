<?php

namespace App\Service;

use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Repository\KPIValueRepository;

/**
 * Service-Klasse für die Berechnung des KPI-Status und Business-Logik.
 *
 * User Story 9: KPI-Dashboard mit Ampellogik (grün, gelb, rot).
 */
class KPIStatusService
{
    public function __construct(
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Berechnet den Status einer KPI (green, yellow, red).
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return string Status: 'green', 'yellow', 'red'
     */
    public function getKpiStatus(KPI $kpi): string
    {
        $currentPeriod = new Period($kpi->getCurrentPeriod());

        // Prüfen ob bereits ein Wert für den aktuellen Zeitraum existiert
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

        if ($existingValue) {
            return 'green'; // Wert erfasst - alles okay
        }

        // Berechnen wie lange bis zur Fälligkeit bzw. wie lange überfällig
        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();

        $daysDiff = $now->diff($dueDate)->days;
        $isOverdue = $now > $dueDate;

        if ($isOverdue) {
            return 'red'; // Überfällig
        }

        if ($daysDiff <= 3) {
            return 'yellow'; // Bald fällig (innerhalb der nächsten 3 Tage)
        }

        return 'green'; // Noch genug Zeit
    }

    /**
     * Prüft ob eine KPI bald fällig ist (innerhalb der nächsten 3 Tage).
     */
    public function isDueSoon(KPI $kpi): bool
    {
        return 'yellow' === $this->getKpiStatus($kpi);
    }

    /**
     * Prüft ob eine KPI überfällig ist.
     */
    public function isOverdue(KPI $kpi): bool
    {
        return 'red' === $this->getKpiStatus($kpi);
    }

    /**
     * Berechnet das Fälligkeitsdatum für eine KPI basierend auf dem Intervall.
     */
    public function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();

        return match ($kpi->getInterval()) {
            KpiInterval::WEEKLY => $this->getNextMonday($now),
            KpiInterval::MONTHLY => $this->getFirstOfNextMonth($now),
            KpiInterval::QUARTERLY => $this->getNextQuarterStart($now),
            default => $now->modify('+1 week'),
        };
    }

    /**
     * Berechnet Tage seit der letzten Fälligkeit (für Erinnerungen).
     *
     * @param KPI $kpi
     *
     * @return int Anzahl Tage seit letzter Fälligkeit (negativ = noch nicht fällig)
     */
    public function getDaysOverdue(KPI $kpi): int
    {
        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();

        $diff = $now->diff($dueDate);

        if ($now < $dueDate) {
            return -$diff->days; // Noch nicht fällig
        }

        return $diff->days; // Überfällig
    }

    /**
     * Ermittelt alle KPIs die für Erinnerungen in Frage kommen.
     *
     * @param array $kpis       Liste von KPIs
     * @param int   $daysBefore Tage vor Fälligkeit für Vorab-Erinnerung
     * @param int   $daysAfter  Tage nach Fälligkeit für Nachhak-Erinnerung
     *
     * @return array KPIs die eine Erinnerung benötigen
     */
    public function getKpisForReminder(array $kpis, int $daysBefore = 3, int $daysAfter = 0): array
    {
        $reminderKpis = [];

        foreach ($kpis as $kpi) {
            $daysOverdue = $this->getDaysOverdue($kpi);
            $currentPeriod = new Period($kpi->getCurrentPeriod());

            // Prüfen ob bereits Wert für aktuellen Zeitraum erfasst
            $hasValue = null !== $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

            if ($hasValue) {
                continue; // Bereits erfasst, keine Erinnerung nötig
            }

            // Vorab-Erinnerung (z.B. 3 Tage vor Fälligkeit)
            if ($daysOverdue === -$daysBefore) {
                $reminderKpis[] = [
                    'kpi' => $kpi,
                    'type' => 'upcoming',
                    'days' => $daysBefore,
                    'message' => "Ihre KPI \"{$kpi->getName()}\" wird in {$daysBefore} Tagen fällig.",
                ];
            }

            // Erinnerung nach Fälligkeit
            if ($daysAfter > 0 && $daysOverdue === $daysAfter) {
                $reminderKpis[] = [
                    'kpi' => $kpi,
                    'type' => 'overdue',
                    'days' => $daysAfter,
                    'message' => "Ihre KPI \"{$kpi->getName()}\" ist seit {$daysAfter} Tagen überfällig.",
                ];
            }

            // Am Fälligkeitstag
            if (0 === $daysOverdue) {
                $reminderKpis[] = [
                    'kpi' => $kpi,
                    'type' => 'due_today',
                    'days' => 0,
                    'message' => "Ihre KPI \"{$kpi->getName()}\" ist heute fällig.",
                ];
            }
        }

        return $reminderKpis;
    }

    /**
     * Ermittelt den nächsten Montag.
     */
    private function getNextMonday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('N'); // 1 = Montag, 7 = Sonntag

        if (1 === $dayOfWeek) {
            // Heute ist Montag - nächster Montag in einer Woche
            return $date->modify('+1 week');
        }

        // Nächsten Montag berechnen
        $daysUntilMonday = 8 - $dayOfWeek;

        return $date->modify("+{$daysUntilMonday} days");
    }

    /**
     * Ermittelt den ersten Tag des nächsten Monats.
     */
    private function getFirstOfNextMonth(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('first day of next month');
    }

    /**
     * Ermittelt den Start des nächsten Quartals.
     */
    private function getNextQuarterStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $currentMonth = (int) $date->format('n');

        $nextQuarterMonth = match (true) {
            $currentMonth <= 3 => 4,  // Q2 starts in April
            $currentMonth <= 6 => 7,  // Q3 starts in July
            $currentMonth <= 9 => 10, // Q4 starts in October
            default => 1,             // Q1 starts in January (next year)
        };

        $year = 1 === $nextQuarterMonth ? (int) $date->format('Y') + 1 : (int) $date->format('Y');

        return new \DateTimeImmutable("{$year}-{$nextQuarterMonth}-01");
    }
}
