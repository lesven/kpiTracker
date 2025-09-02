<?php

namespace App\Domain\Service;

use App\Domain\ValueObject\KPIStatus;
use App\Entity\KPI;
use App\Entity\User;
use App\Repository\KPIValueRepository;

/**
 * Domain Service für intelligente KPI-Erinnerungs-Logic.
 *
 * Implementiert komplexe Geschäftsregeln für:
 * - Erinnerungs-Timing und -Eskalation
 * - Benutzer-spezifische Einstellungen
 * - Kontext-aware Notifications 
 * - Anti-Spam und Intelligente Gruppierung
 * - Priorisierung und Kategorisierung
 */
class KPIReminderDomainService
{
    /**
     * Standard-Erinnerungs-Intervalle (in Tagen).
     */
    private const DEFAULT_WARNING_DAYS = 3;
    private const DEFAULT_OVERDUE_ESCALATION = [1, 3, 7, 14]; // Tage nach Fälligkeit
    
    /**
     * Anti-Spam Limits.
     */
    private const MAX_DAILY_REMINDERS = 5;
    private const MIN_HOURS_BETWEEN_REMINDERS = 4;

    public function __construct(
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Ermittelt alle KPIs die eine Erinnerung benötigen.
     *
     * @param array $kpis Liste von KPIs zur Prüfung
     * @param array $reminderConfig Erinnerungs-Konfiguration
     * @return array Gruppierte Erinnerungs-Objekte
     */
    public function getKpisNeedingReminder(array $kpis, array $reminderConfig = []): array
    {
        $config = $this->mergeReminderConfig($reminderConfig);
        $reminderGroups = [
            'upcoming' => [],
            'due_today' => [],
            'overdue' => [],
            'critical' => [],
        ];

        foreach ($kpis as $kpi) {
            $reminderData = $this->analyzeKpiForReminder($kpi, $config);
            
            if ($reminderData !== null) {
                $category = $this->categorizeReminder($reminderData);
                $reminderGroups[$category][] = $reminderData;
            }
        }

        return $this->prioritizeAndLimitReminders($reminderGroups, $config);
    }

    /**
     * Prüft ob für eine spezifische KPI eine Erinnerung gesendet werden sollte.
     *
     * @param KPI $kpi Die zu prüfende KPI
     * @param array $userPreferences Benutzer-spezifische Einstellungen
     * @param ?\DateTimeImmutable $lastReminderSent Zeitpunkt der letzten Erinnerung
     * @return bool true wenn Erinnerung gesendet werden soll
     */
    public function shouldSendReminder(
        KPI $kpi, 
        array $userPreferences = [], 
        ?\DateTimeImmutable $lastReminderSent = null
    ): bool {
        // Anti-Spam Prüfung
        if ($lastReminderSent && $this->isTooSoon($lastReminderSent)) {
            return false;
        }

        // Benutzer-Präferenzen prüfen
        if (!$this->isReminderEnabled($kpi, $userPreferences)) {
            return false;
        }

        // Status-basierte Erinnerungs-Berechtigung
        $reminderData = $this->analyzeKpiForReminder($kpi);
        
        return $reminderData !== null && $this->meetsReminderCriteria($reminderData, $userPreferences);
    }

    /**
     * Berechnet den optimalen Zeitpunkt für die nächste Erinnerung.
     *
     * @param KPI $kpi Die KPI für die Erinnerung
     * @param string $reminderType Art der Erinnerung (upcoming, overdue, etc.)
     * @return ?\DateTimeImmutable Optimaler Zeitpunkt oder null wenn keine weitere Erinnerung
     */
    public function calculateNextReminderTime(KPI $kpi, string $reminderType): ?\DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        
        return match ($reminderType) {
            'upcoming' => $this->calculateUpcomingReminderTime($kpi),
            'due_today' => $now->setTime(9, 0), // Morgens um 9 Uhr
            'overdue' => $this->calculateOverdueEscalation($kpi),
            'critical' => $now->modify('+2 hours'), // Kritische sofort, dann alle 2h
            default => null,
        };
    }

    /**
     * Erstellt personalisierte Erinnerungs-Nachrichten.
     *
     * @param array $reminderData Erinnerungs-Daten aus analyzeKpiForReminder()
     * @param User $user Empfänger der Erinnerung
     * @return array Strukturierte Nachricht mit Titel, Text, Aktionen
     */
    public function createReminderMessage(array $reminderData, User $user): array
    {
        $kpi = $reminderData['kpi'];
        $type = $reminderData['type'];
        $urgencyLevel = $this->calculateUrgencyLevel($reminderData);

        $baseMessage = $this->generateBaseMessage($kpi, $type, $reminderData);
        $personalizedMessage = $this->personalizeMessage($baseMessage, $user);
        $actionItems = $this->generateActionItems($kpi, $type);

        return [
            'title' => $personalizedMessage['title'],
            'message' => $personalizedMessage['body'],
            'urgency' => $urgencyLevel,
            'type' => $type,
            'kpi_id' => $kpi->getId(),
            'actions' => $actionItems,
            'context' => [
                'days_since_due' => $reminderData['days_overdue'] ?? null,
                'period' => $reminderData['period'] ?? null,
                'escalation_level' => $reminderData['escalation_level'] ?? 1,
            ],
        ];
    }

    /**
     * Berechnet Erinnerungs-Statistiken für Dashboard/Reporting.
     *
     * @param User $user Benutzer für den die Statistiken berechnet werden
     * @param int $timeframeDays Zeitraum in Tagen für Analyse
     * @return array Statistische Auswertung
     */
    public function calculateReminderStatistics(User $user, int $timeframeDays = 30): array
    {
        $userKpis = $user->getKpis()->toArray();
        
        if (empty($userKpis)) {
            return ['status' => 'no_kpis'];
        }

        $stats = [
            'total_kpis' => count($userKpis),
            'reminders_needed' => 0,
            'overdue_count' => 0,
            'upcoming_count' => 0,
            'critical_count' => 0,
            'completion_rate' => 0,
        ];

        $reminderData = $this->getKpisNeedingReminder($userKpis);
        
        foreach ($reminderData as $category => $reminders) {
            $stats[$category . '_count'] = count($reminders);
            $stats['reminders_needed'] += count($reminders);
        }

        // Completion Rate berechnen
        $completedKpis = $stats['total_kpis'] - $stats['reminders_needed'];
        $stats['completion_rate'] = $stats['total_kpis'] > 0 
            ? round(($completedKpis / $stats['total_kpis']) * 100, 1) 
            : 0;

        $stats['health_score'] = $this->calculateHealthScore($stats);

        return $stats;
    }

    /**
     * Analysiert eine einzelne KPI für Erinnerungs-Bedarf.
     */
    private function analyzeKpiForReminder(KPI $kpi, array $config = []): ?array
    {
        $currentPeriod = $kpi->getCurrentPeriod();
        $hasValue = null !== $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

        if ($hasValue) {
            return null; // Keine Erinnerung nötig
        }

        $dueDate = $this->calculateDueDate($kpi);
        $now = new \DateTimeImmutable();
        $daysDifference = $this->calculateDaysDifference($now, $dueDate);
        
        $warningDays = $config['warning_days'] ?? self::DEFAULT_WARNING_DAYS;

        // Bestimme Erinnerungs-Typ
        $type = null;
        $escalationLevel = 1;

        if ($daysDifference < 0) { // Überfällig
            $daysOverdue = abs($daysDifference);
            $type = 'overdue';
            $escalationLevel = $this->calculateEscalationLevel($daysOverdue);
        } elseif ($daysDifference === 0) { // Heute fällig
            $type = 'due_today';
        } elseif ($daysDifference <= $warningDays) { // Bald fällig
            $type = 'upcoming';
        }

        if ($type === null) {
            return null; // Noch zu früh für Erinnerung
        }

        return [
            'kpi' => $kpi,
            'type' => $type,
            'days_overdue' => $daysDifference < 0 ? abs($daysDifference) : null,
            'days_until_due' => $daysDifference > 0 ? $daysDifference : null,
            'due_date' => $dueDate,
            'period' => $currentPeriod->format(),
            'escalation_level' => $escalationLevel,
            'urgency' => $this->calculateUrgencyScore($type, $escalationLevel, $kpi),
        ];
    }

    /**
     * Führt Standard-Konfiguration mit benutzerdefinierten Einstellungen zusammen.
     */
    private function mergeReminderConfig(array $config): array
    {
        return array_merge([
            'warning_days' => self::DEFAULT_WARNING_DAYS,
            'max_daily_reminders' => self::MAX_DAILY_REMINDERS,
            'min_hours_between' => self::MIN_HOURS_BETWEEN_REMINDERS,
            'enable_escalation' => true,
            'group_similar' => true,
        ], $config);
    }

    /**
     * Kategorisiert Erinnerung basierend auf Typ und Dringlichkeit.
     */
    private function categorizeReminder(array $reminderData): string
    {
        $type = $reminderData['type'];
        $escalationLevel = $reminderData['escalation_level'] ?? 1;
        
        if ($type === 'overdue' && $escalationLevel >= 3) {
            return 'critical';
        }

        return $type;
    }

    /**
     * Priorisiert und limitiert Erinnerungen um Spam zu vermeiden.
     */
    private function prioritizeAndLimitReminders(array $reminderGroups, array $config): array
    {
        // Nach Dringlichkeit sortieren
        foreach ($reminderGroups as &$group) {
            usort($group, fn($a, $b) => $b['urgency'] <=> $a['urgency']);
        }

        // Limitierung anwenden
        $maxReminders = $config['max_daily_reminders'] ?? self::MAX_DAILY_REMINDERS;
        $totalReminders = 0;

        foreach ($reminderGroups as $category => &$group) {
            if ($totalReminders >= $maxReminders) {
                $group = [];
                continue;
            }

            $allowedInCategory = min(count($group), $maxReminders - $totalReminders);
            $group = array_slice($group, 0, $allowedInCategory);
            $totalReminders += count($group);
        }

        return $reminderGroups;
    }

    /**
     * Anti-Spam: Prüft ob seit letzter Erinnerung genug Zeit vergangen ist.
     */
    private function isTooSoon(?\DateTimeImmutable $lastReminderSent): bool
    {
        if ($lastReminderSent === null) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $hoursDifference = $now->diff($lastReminderSent)->h + ($now->diff($lastReminderSent)->days * 24);

        return $hoursDifference < self::MIN_HOURS_BETWEEN_REMINDERS;
    }

    /**
     * Prüft ob Erinnerungen für diese KPI aktiviert sind.
     */
    private function isReminderEnabled(KPI $kpi, array $userPreferences): bool
    {
        $globalEnabled = $userPreferences['reminders_enabled'] ?? true;
        $kpiSpecific = $userPreferences['kpi_reminders'][$kpi->getId()] ?? true;

        return $globalEnabled && $kpiSpecific;
    }

    /**
     * Prüft ob Erinnerungs-Kriterien erfüllt sind.
     */
    private function meetsReminderCriteria(array $reminderData, array $userPreferences): bool
    {
        $type = $reminderData['type'];
        $urgency = $reminderData['urgency'];
        
        $minUrgency = $userPreferences['min_urgency_level'] ?? 1;
        
        return $urgency >= $minUrgency;
    }

    /**
     * Berechnet Fälligkeitsdatum der KPI.
     */
    private function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        return $kpi->getNextDueDate();
    }

    /**
     * Berechnet Tage-Differenz zwischen zwei Daten.
     */
    private function calculateDaysDifference(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $interval = $from->diff($to);
        $days = (int) $interval->days;
        
        return $from > $to ? -$days : $days;
    }

    /**
     * Berechnet Eskalationsstufe basierend auf Tagen Überfälligkeit.
     */
    private function calculateEscalationLevel(int $daysOverdue): int
    {
        foreach (self::DEFAULT_OVERDUE_ESCALATION as $level => $threshold) {
            if ($daysOverdue <= $threshold) {
                return $level + 1;
            }
        }

        return count(self::DEFAULT_OVERDUE_ESCALATION) + 1; // Maximale Eskalation
    }

    /**
     * Berechnet Dringlichkeits-Score für Priorisierung.
     */
    private function calculateUrgencyScore(string $type, int $escalationLevel, KPI $kpi): int
    {
        $baseScore = match ($type) {
            'critical' => 100,
            'overdue' => 50,
            'due_today' => 30,
            'upcoming' => 10,
            default => 1,
        };

        // Escalation multiplier
        $escalationMultiplier = 1 + ($escalationLevel * 0.5);
        
        // Business Impact (beispielhaft über KPI-Name)
        $businessImpact = str_contains(strtolower($kpi->getName()), 'umsatz') ? 2 : 1;

        return (int) ($baseScore * $escalationMultiplier * $businessImpact);
    }

    /**
     * Berechnet optimalen Zeitpunkt für Vorab-Erinnerung.
     */
    private function calculateUpcomingReminderTime(KPI $kpi): \DateTimeImmutable
    {
        $dueDate = $this->calculateDueDate($kpi);
        
        // 1 Tag vor Fälligkeit, morgens um 9 Uhr
        return $dueDate->modify('-1 day')->setTime(9, 0);
    }

    /**
     * Berechnet nächste Eskalations-Erinnerung für überfällige KPIs.
     */
    private function calculateOverdueEscalation(KPI $kpi): ?\DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $dueDate = $this->calculateDueDate($kpi);
        $daysOverdue = abs($this->calculateDaysDifference($now, $dueDate));
        
        // Nächster Eskalations-Punkt
        foreach (self::DEFAULT_OVERDUE_ESCALATION as $threshold) {
            if ($daysOverdue < $threshold) {
                return $dueDate->modify("+{$threshold} days")->setTime(10, 0);
            }
        }

        // Wöchentliche Erinnerungen nach letzter Eskalation
        return $now->modify('+1 week')->setTime(10, 0);
    }

    /**
     * Generiert Basis-Nachricht für Erinnerung.
     */
    private function generateBaseMessage(KPI $kpi, string $type, array $data): array
    {
        $kpiName = $kpi->getName();
        
        return match ($type) {
            'upcoming' => [
                'title' => "KPI '{$kpiName}' wird bald fällig",
                'body' => "Ihre KPI '{$kpiName}' wird in {$data['days_until_due']} Tagen fällig. Jetzt wäre ein guter Zeitpunkt für die Erfassung.",
            ],
            'due_today' => [
                'title' => "KPI '{$kpiName}' ist heute fällig",
                'body' => "Ihre KPI '{$kpiName}' ist heute fällig. Bitte erfassen Sie den aktuellen Wert.",
            ],
            'overdue' => [
                'title' => "KPI '{$kpiName}' ist überfällig",
                'body' => "Ihre KPI '{$kpiName}' ist seit {$data['days_overdue']} Tagen überfällig. Bitte erfassen Sie den Wert schnellstmöglich.",
            ],
            'critical' => [
                'title' => "DRINGEND: KPI '{$kpiName}' stark überfällig",
                'body' => "Ihre kritische KPI '{$kpiName}' ist seit {$data['days_overdue']} Tagen überfällig und benötigt sofortige Aufmerksamkeit.",
            ],
            default => [
                'title' => "KPI-Erinnerung: {$kpiName}",
                'body' => "Bitte überprüfen Sie den Status Ihrer KPI '{$kpiName}'.",
            ],
        };
    }

    /**
     * Personalisiert Nachricht für spezifischen Benutzer.
     */
    private function personalizeMessage(array $baseMessage, User $user): array
    {
        $firstName = $user->getFirstName() ?? 'Benutzer';
        
        return [
            'title' => $baseMessage['title'],
            'body' => "Hallo {$firstName},\n\n" . $baseMessage['body'],
        ];
    }

    /**
     * Generiert kontextuelle Aktions-Buttons für Erinnerung.
     */
    private function generateActionItems(KPI $kpi, string $type): array
    {
        $kpiId = $kpi->getId();
        
        $baseActions = [
            [
                'label' => 'Wert erfassen',
                'url' => "/kpi/{$kpiId}/add-value",
                'type' => 'primary',
            ],
            [
                'label' => 'KPI anzeigen',
                'url' => "/kpi/{$kpiId}",
                'type' => 'secondary',
            ],
        ];

        if ($type === 'critical' || $type === 'overdue') {
            array_unshift($baseActions, [
                'label' => 'Erinnerung stummschalten (24h)',
                'action' => 'snooze_reminder',
                'type' => 'muted',
            ]);
        }

        return $baseActions;
    }

    /**
     * Berechnet Dringlichkeitsstufe für UI.
     */
    private function calculateUrgencyLevel(array $reminderData): string
    {
        $urgencyScore = $reminderData['urgency'];
        
        return match (true) {
            $urgencyScore >= 80 => 'critical',
            $urgencyScore >= 40 => 'high',
            $urgencyScore >= 20 => 'medium',
            default => 'low',
        };
    }

    /**
     * Berechnet Gesundheits-Score basierend auf Erinnerungs-Statistiken.
     */
    private function calculateHealthScore(array $stats): int
    {
        if ($stats['total_kpis'] === 0) {
            return 100;
        }

        $completionRate = $stats['completion_rate'];
        $criticalPercentage = ($stats['critical_count'] / $stats['total_kpis']) * 100;
        $overduePercentage = ($stats['overdue_count'] / $stats['total_kpis']) * 100;

        // Score-Berechnung
        $healthScore = $completionRate;
        $healthScore -= ($criticalPercentage * 2); // Kritische KPIs wiegen doppelt
        $healthScore -= $overduePercentage;

        return max(0, min(100, (int) $healthScore));
    }
}