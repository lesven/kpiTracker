<?php

namespace App\Service;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * KPI-Aggregat: Zentrale Business-Logic für alle KPI-bezogenen Operationen.
 *
 * Diese Klasse konsolidiert die verstreute Business-Logic aus KPIService, 
 * KPIStatusService und KPIValueService in einer einzigen, kohärenten Komponente.
 *
 * Verantwortlichkeiten:
 * - Status-Berechnung (Ampellogik: grün, gelb, rot)
 * - Statistik-Generierung und Trend-Analyse
 * - KPI-Validierung vor dem Speichern
 * - Verwaltung von KPI-Werten und Datei-Uploads
 * - Erinnerungs-Logic für fällige KPIs
 * - Fälligkeits-Berechnung basierend auf Intervallen
 */
class KPIAggregate
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KPIValueRepository $kpiValueRepository,
        private FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Berechnet den aktuellen Status einer KPI basierend auf Fälligkeits-Ampellogik.
     *
     * Logik:
     * - GRÜN: Wert für aktuellen Zeitraum bereits erfasst
     * - ROT: KPI ist überfällig (Fälligkeitsdatum überschritten)
     * - GELB: KPI ist bald fällig (innerhalb der nächsten 3 Tage)
     * - GRÜN: Noch genügend Zeit bis zur Fälligkeit
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @return string Status-Code: 'green', 'yellow' oder 'red'
     */
    public function getKpiStatus(KPI $kpi): string
    {
        $currentPeriod = $kpi->getCurrentPeriod();
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

        // Bereits erfasst = alles in Ordnung
        if ($existingValue) {
            return 'green';
        }

        // Fälligkeitsdatum berechnen und mit aktuellem Datum vergleichen
        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();

        $daysDiff = $now->diff($dueDate)->days;
        $isOverdue = $now > $dueDate;

        if ($isOverdue) {
            return 'red'; // Überfällig
        }

        if ($daysDiff <= 3) {
            return 'yellow'; // Bald fällig (Vorwarnung)
        }

        return 'green'; // Noch genug Zeit
    }

    /**
     * Prüft, ob für die KPI bereits ein Wert im aktuellen Zeitraum erfasst wurde.
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @return bool true wenn Wert existiert, false wenn nicht
     */
    public function hasCurrentValue(KPI $kpi): bool
    {
        $currentPeriod = $kpi->getCurrentPeriod();
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

        return null !== $existingValue;
    }

    /**
     * Berechnet umfassende Statistiken für eine KPI basierend auf historischen Daten.
     *
     * Generierte Metriken:
     * - Gesamtanzahl erfasster Werte
     * - Durchschnittswert (auf 2 Dezimalstellen gerundet)
     * - Minimum und Maximum der Werte
     * - Letzter erfasster Wert (chronologisch neueste)
     * - Trend-Indikator basierend auf den letzten 3 Werten
     *
     * @param KPI $kpi Die KPI für die Statistik-Berechnung
     * @return array Assoziatives Array mit allen statistischen Kennzahlen
     */
    public function getKpiStatistics(KPI $kpi): array
    {
        $values = $this->kpiValueRepository->findByKPI($kpi);

        // Keine Daten vorhanden - leere Statistik zurückgeben
        if (empty($values)) {
            return [
                'total_entries' => 0,
                'average_value' => null,
                'min_value' => null,
                'max_value' => null,
                'latest_value' => null,
                'trend' => 'no_data',
            ];
        }

        // Alle Werte in numerisches Format konvertieren für Berechnungen
        $numericValues = array_map(fn ($v) => $v->getValueAsFloat(), $values);

        return [
            'total_entries' => count($values),
            'average_value' => round(array_sum($numericValues) / count($numericValues), 2),
            'min_value' => min($numericValues),
            'max_value' => max($numericValues),
            'latest_value' => $values[0], // Repository liefert bereits chronologisch sortiert
            'trend' => $this->calculateTrend($numericValues),
        ];
    }

    /**
     * Validiert eine KPI vor dem Speichern auf Vollständigkeit und Konsistenz.
     *
     * Prüfungen:
     * - KPI-Name darf nicht leer sein (nach Trimmen)
     * - Intervall muss gültig und gesetzt sein
     * - KPI muss einem Benutzer zugeordnet sein
     *
     * @param KPI $kpi Die zu validierende KPI
     * @return array Liste der Validierungsfehler (leer = valide)
     */
    public function validateKpi(KPI $kpi): array
    {
        $errors = [];

        // Name-Validierung: muss vorhanden und nicht nur Whitespace sein
        if (!$kpi->getName() || 0 === mb_strlen(mb_trim($kpi->getName()))) {
            $errors[] = 'KPI-Name ist erforderlich.';
        }

        // Intervall-Validierung: muss gesetzt und gültig sein
        if (null === $kpi->getInterval()) {
            $errors[] = 'Ungültiges Intervall gewählt.';
        }

        // Benutzer-Zuordnung: jede KPI muss einem User gehören
        if (!$kpi->getUser()) {
            $errors[] = 'KPI muss einem Benutzer zugeordnet sein.';
        }

        return $errors;
    }

    /**
     * Speichert einen neuen KPI-Wert und verarbeitet optional hochgeladene Dateien.
     *
     * Arbeitsablauf:
     * 1. Prüfung auf bereits existierenden Wert für den Zeitraum (Duplikat-Schutz)
     * 2. Persistierung des neuen Wertes in der Datenbank
     * 3. Optional: Verarbeitung hochgeladener Dateien
     * 4. Rückgabe des Ergebnis-Status mit Details
     *
     * @param KPIValue $kpiValue Der zu speichernde KPI-Wert
     * @param array|null $uploadedFiles Optional hochgeladene Dateien
     * @return array Ergebnis mit Status und optional Upload-Statistiken
     */
    public function addValue(KPIValue $kpiValue, ?array $uploadedFiles = null): array
    {
        // Duplikat-Prüfung: bereits Wert für diesen Zeitraum vorhanden?
        $existing = $this->kpiValueRepository->findByKpiAndPeriod(
            $kpiValue->getKpi(),
            $kpiValue->getPeriod(),
        );

        if (null !== $existing) {
            return ['status' => 'duplicate', 'existing' => $existing];
        }

        // Neuen Wert in Datenbank speichern
        $this->entityManager->persist($kpiValue);
        $this->entityManager->flush();

        // Optional: Datei-Uploads verarbeiten
        $uploadStats = [];
        if ($uploadedFiles) {
            $uploadStats = $this->fileUploadService->handleFileUploads($uploadedFiles, $kpiValue);
            $this->entityManager->flush(); // Datei-Referenzen speichern
        }

        return ['status' => 'success', 'upload' => $uploadStats];
    }

    /**
     * Prüft, ob eine KPI bald fällig ist (Status = gelb).
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @return bool true wenn KPI in den nächsten 3 Tagen fällig wird
     */
    public function isDueSoon(KPI $kpi): bool
    {
        return 'yellow' === $this->getKpiStatus($kpi);
    }

    /**
     * Prüft, ob eine KPI überfällig ist (Status = rot).
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @return bool true wenn KPI bereits überfällig ist
     */
    public function isOverdue(KPI $kpi): bool
    {
        return 'red' === $this->getKpiStatus($kpi);
    }

    /**
     * Berechnet das nächste Fälligkeitsdatum einer KPI basierend auf ihrem Intervall.
     *
     * Intervall-Logik:
     * - WEEKLY: Nächster Montag
     * - MONTHLY: Erster Tag des nächsten Monats
     * - QUARTERLY: Erster Tag des nächsten Quartals (Jan, Apr, Jul, Okt)
     * - Default: Eine Woche ab heute
     *
     * @param KPI $kpi Die KPI für die Fälligkeits-Berechnung
     * @return \DateTimeImmutable Das berechnete Fälligkeitsdatum
     */
    public function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();

        return match ($kpi->getInterval()) {
            \App\Domain\ValueObject\KpiInterval::WEEKLY => $this->getNextMonday($now),
            \App\Domain\ValueObject\KpiInterval::MONTHLY => $this->getFirstOfNextMonth($now),
            \App\Domain\ValueObject\KpiInterval::QUARTERLY => $this->getNextQuarterStart($now),
            default => $now->modify('+1 week'), // Fallback für unbekannte Intervalle
        };
    }

    /**
     * Berechnet die Anzahl Tage seit/bis zur Fälligkeit.
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @return int Positive Zahl = Tage überfällig, negative Zahl = Tage bis Fälligkeit
     */
    public function getDaysOverdue(KPI $kpi): int
    {
        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();

        $diff = $now->diff($dueDate);

        if ($now < $dueDate) {
            return -$diff->days; // Noch nicht fällig (negative Tage bis Fälligkeit)
        }

        return $diff->days; // Überfällig (positive Tage seit Fälligkeit)
    }

    /**
     * Ermittelt alle KPIs aus einer Liste, die eine Erinnerung benötigen.
     *
     * Erinnerungs-Typen:
     * - 'upcoming': X Tage vor Fälligkeit (Vorwarnung)
     * - 'due_today': Am Fälligkeitstag selbst
     * - 'overdue': X Tage nach Fälligkeit (Mahnung)
     *
     * @param array $kpis Liste der zu prüfenden KPIs
     * @param int $daysBefore Tage vor Fälligkeit für Vorab-Erinnerung (Standard: 3)
     * @param int $daysAfter Tage nach Fälligkeit für Nachhak-Erinnerung (Standard: 0)
     * @return array Liste von Erinnerungs-Objekten mit KPI, Typ und Nachricht
     */
    public function getKpisForReminder(array $kpis, int $daysBefore = 3, int $daysAfter = 0): array
    {
        $reminderKpis = [];

        foreach ($kpis as $kpi) {
            $daysOverdue = $this->getDaysOverdue($kpi);
            $currentPeriod = $kpi->getCurrentPeriod();

            // Bereits erfasst = keine Erinnerung nötig
            $hasValue = null !== $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);
            if ($hasValue) {
                continue;
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

            // Nachhak-Erinnerung (nach Fälligkeit)
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
     * Berechnet den Trend einer KPI basierend auf den letzten Werten.
     *
     * Trend-Logik:
     * - Mindestens 2 Werte erforderlich für Trend-Berechnung
     * - Verwendet die letzten 3 Werte (falls verfügbar)
     * - Berechnet prozentuale Veränderung zwischen ältestem und neuestem Wert
     * - Schwellwerte: >5% = steigend, <-5% = fallend, dazwischen = stabil
     *
     * @param array $values Array numerischer Werte (chronologisch neueste zuerst)
     * @return string Trend-Indikator: 'rising', 'falling', 'stable', 'insufficient_data'
     */
    private function calculateTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'insufficient_data';
        }

        // Maximal die letzten 3 Werte für Trend-Berechnung verwenden
        $recentValues = array_slice($values, 0, min(3, count($values)));

        if (count($recentValues) < 2) {
            return 'insufficient_data';
        }

        // Ältester und neuester Wert für Vergleich
        $first = $recentValues[count($recentValues) - 1]; // Ältester der betrachteten Werte
        $last = $recentValues[0]; // Neuester Wert (Index 0)

        // Prozentuale Veränderung berechnen
        $percentageChange = (($last - $first) / $first) * 100;

        if ($percentageChange > 5) {
            return 'rising';
        } elseif ($percentageChange < -5) {
            return 'falling';
        }

        return 'stable';
    }

    /**
     * Ermittelt den nächsten Montag ab einem gegebenen Datum.
     *
     * Logik:
     * - Ist heute Montag: nächster Montag in einer Woche
     * - Andernfalls: nächster kommender Montag
     *
     * @param \DateTimeImmutable $date Ausgangsdatum
     * @return \DateTimeImmutable Datum des nächsten Montags
     */
    private function getNextMonday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('N'); // ISO-8601: 1=Montag, 7=Sonntag

        if (1 === $dayOfWeek) {
            // Heute ist bereits Montag - nächster Montag in einer Woche
            return $date->modify('+1 week');
        }

        // Tage bis zum nächsten Montag berechnen
        $daysUntilMonday = 8 - $dayOfWeek;

        return $date->modify("+{$daysUntilMonday} days");
    }

    /**
     * Ermittelt den ersten Tag des nächsten Monats.
     *
     * @param \DateTimeImmutable $date Ausgangsdatum
     * @return \DateTimeImmutable Erster Tag des Folgemonats
     */
    private function getFirstOfNextMonth(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('first day of next month');
    }

    /**
     * Ermittelt den Beginn des nächsten Quartals.
     *
     * Quartals-Definitionen:
     * - Q1: Januar (Monat 1)
     * - Q2: April (Monat 4)
     * - Q3: Juli (Monat 7)
     * - Q4: Oktober (Monat 10)
     *
     * @param \DateTimeImmutable $date Ausgangsdatum
     * @return \DateTimeImmutable Erster Tag des nächsten Quartals
     */
    private function getNextQuarterStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $currentMonth = (int) $date->format('n'); // Monat ohne führende Null (1-12)

        // Nächsten Quartalsbeginn basierend auf aktuellem Monat ermitteln
        $nextQuarterMonth = match (true) {
            $currentMonth <= 3 => 4,  // Q1 → Q2 (April)
            $currentMonth <= 6 => 7,  // Q2 → Q3 (Juli)
            $currentMonth <= 9 => 10, // Q3 → Q4 (Oktober)
            default => 1,             // Q4 → Q1 (Januar nächstes Jahr)
        };

        // Jahr anpassen wenn Übergang zu Q1 des nächsten Jahres
        $year = 1 === $nextQuarterMonth ? (int) $date->format('Y') + 1 : (int) $date->format('Y');

        return new \DateTimeImmutable("{$year}-{$nextQuarterMonth}-01");
    }
}