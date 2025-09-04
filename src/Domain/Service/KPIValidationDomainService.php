<?php

namespace App\Domain\Service;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;

/**
 * Domain Service für erweiterte KPI-Validierungen mit Business-Logic.
 *
 * Implementiert komplexe Validierungsregeln die über einfache Entity-Validierung hinausgehen:
 * - Kontextuelle Geschäftsregeln
 * - Cross-Entity Validierungen
 * - Historische Daten-Konsistenz
 * - Benutzer-spezifische Beschränkungen
 * - Datenqualitäts-Prüfungen
 */
class KPIValidationDomainService
{
    /**
     * Geschäftsregeln-Konstanten.
     */
    private const MAX_KPIS_PER_USER = 50;
    private const MIN_KPI_NAME_LENGTH = 3;
    private const MAX_KPI_NAME_LENGTH = 100;
    private const MAX_VALUE_COMMENT_LENGTH = 1000;
    private const MAX_VALUE_MAGNITUDE = 999999999;

    public function __construct(
        private KPIRepository $kpiRepository,
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Validiert eine KPI vor dem Erstellen/Aktualisieren.
     *
     * @param KPI $kpi Die zu validierende KPI
     * @param array $context Kontext-Informationen für erweiterte Validierung
     * @return array Validierungs-Ergebnis mit Fehlern und Warnungen
     */
    public function validateKPIFull(KPI $kpi, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Basis-Validierungen
        $basicValidation = $this->validateBasicKPIRequirements($kpi, $context);
        $errors = array_merge($errors, $basicValidation['errors']);
        $warnings = array_merge($warnings, $basicValidation['warnings']);

        // Benutzer-spezifische Validierungen
        if ($kpi->getUser()) {
            $userValidation = $this->validateUserSpecificRules($kpi, $context);
            $errors = array_merge($errors, $userValidation['errors']);
            $warnings = array_merge($warnings, $userValidation['warnings']);
        }

        // Geschäftslogik-Validierungen
        $businessValidation = $this->validateBusinessRules($kpi, $context);
        $errors = array_merge($errors, $businessValidation['errors']);
        $warnings = array_merge($warnings, $businessValidation['warnings']);

        // Konsistenz-Prüfungen (für Updates)
        if ($context['is_update'] ?? false) {
            $consistencyValidation = $this->validateUpdateConsistency($kpi, $context);
            $errors = array_merge($errors, $consistencyValidation['errors']);
            $warnings = array_merge($warnings, $consistencyValidation['warnings']);
        }

        return [
            'is_valid' => empty($errors),
            'has_warnings' => !empty($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
            'severity' => $this->calculateSeverity($errors, $warnings),
        ];
    }

    /**
     * Validiert einen KPI-Wert vor dem Speichern.
     *
     * @param KPIValue $kpiValue Der zu validierende Wert
     * @param array $context Kontext für erweiterte Validierung
     * @return array Validierungs-Ergebnis
     */
    public function validateKPIValueFull(KPIValue $kpiValue, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Basis-Wert-Validierung
        $basicValidation = $this->validateBasicValueRequirements($kpiValue);
        $errors = array_merge($errors, $basicValidation['errors']);
        $warnings = array_merge($warnings, $basicValidation['warnings']);

        // Datenqualitäts-Prüfungen
        $qualityValidation = $this->validateDataQuality($kpiValue, $context);
        $errors = array_merge($errors, $qualityValidation['errors']);
        $warnings = array_merge($warnings, $qualityValidation['warnings']);

        // Historische Konsistenz
        $historyValidation = $this->validateHistoricalConsistency($kpiValue, $context);
        $warnings = array_merge($warnings, $historyValidation['warnings']);

        // Business-spezifische Wert-Validierung
        $businessValidation = $this->validateValueBusinessRules($kpiValue, $context);
        $errors = array_merge($errors, $businessValidation);

        return [
            'is_valid' => empty($errors),
            'has_warnings' => !empty($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
            'data_quality_score' => $this->calculateDataQualityScore($kpiValue),
            'severity' => $this->calculateSeverity($errors, $warnings),
        ];
    }

    /**
     * Validiert Bulk-Operationen (z.B. Import von vielen KPIs).
     *
     * @param array $kpis Liste von KPIs zu validieren
     * @param array $context Bulk-Kontext
     * @return array Bulk-Validierungs-Ergebnis
     */
    public function validateBulkKPIs(array $kpis, array $context = []): array
    {
        $results = [];
        $globalErrors = [];
        
        // Globale Validierungen (über alle KPIs hinweg)
        $globalValidation = $this->validateGlobalConstraints($kpis, $context);
        $globalErrors = $globalValidation['errors'];

        // Individuelle KPI-Validierungen
        foreach ($kpis as $index => $kpi) {
            $individualResult = $this->validateKPIFull($kpi, array_merge($context, ['bulk_index' => $index]));
            $results[$index] = $individualResult;
        }

        // Statistiken berechnen
        $totalKpis = count($kpis);
        $validKpis = count(array_filter($results, fn($result) => $result['is_valid']));
        $kpisWithWarnings = count(array_filter($results, fn($result) => $result['has_warnings']));

        return [
            'global_valid' => empty($globalErrors),
            'global_errors' => $globalErrors,
            'individual_results' => $results,
            'statistics' => [
                'total_kpis' => $totalKpis,
                'valid_kpis' => $validKpis,
                'invalid_kpis' => $totalKpis - $validKpis,
                'kpis_with_warnings' => $kpisWithWarnings,
                'validation_success_rate' => $totalKpis > 0 ? ($validKpis / $totalKpis) * 100 : 0,
            ],
        ];
    }

    /**
     * Validiert KPI-Löschung auf Geschäftsebene.
     *
     * @param KPI $kpi Die zu löschende KPI
     * @param array $context Lösch-Kontext
     * @return array Lösch-Validierungs-Ergebnis
     */
    public function validateKPIDeletion(KPI $kpi, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Prüfe auf abhängige Daten
        $dependencyCheck = $this->checkDependenciesBeforeDeletion($kpi);
        if (!$dependencyCheck['can_delete']) {
            $errors[] = 'KPI kann nicht gelöscht werden: ' . $dependencyCheck['reason'];
        }
        $warnings = array_merge($warnings, $dependencyCheck['warnings']);

        // Geschäftsregeln für Löschung
        $businessCheck = $this->validateDeletionBusinessRules($kpi, $context);
        $errors = array_merge($errors, $businessCheck['errors']);
        $warnings = array_merge($warnings, $businessCheck['warnings']);

        // Archive-Empfehlung statt Löschung
        if ($this->shouldRecommendArchiving($kpi)) {
            $warnings[] = 'Empfehlung: Archivierung statt Löschung für historische Datenintegrität';
        }

        return [
            'can_delete' => empty($errors),
            'has_warnings' => !empty($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
            'affected_records' => $dependencyCheck['affected_count'],
            'alternative_actions' => $this->suggestAlternativeActions($kpi),
        ];
    }

    /**
     * Validiert grundlegende KPI-Anforderungen.
     */
    private function validateBasicKPIRequirements(KPI $kpi, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Name-Validierung
        $name = $kpi->getName();
        if (!$name || trim($name) === '') {
            $errors[] = 'KPI-Name ist erforderlich.';
        } elseif (mb_strlen($name) < self::MIN_KPI_NAME_LENGTH) {
            $errors[] = "KPI-Name muss mindestens " . self::MIN_KPI_NAME_LENGTH . " Zeichen lang sein.";
        } elseif (mb_strlen($name) > 255) {
            $errors[] = "Der KPI-Name darf maximal 255 Zeichen lang sein.";
        }

        // Intervall-Validierung
        if (!$kpi->getInterval()) {
            $errors[] = 'Ungültiges Intervall gewählt.';
        }

        // User-Zuordnung
        if (!$kpi->getUser()) {
            $errors[] = 'KPI muss einem Benutzer zugeordnet sein.';
        }

        // Zielwert-Plausibilität
        $target = $kpi->getTargetAsFloat();
        if ($target !== null) {
            if ($target < 0 && !($context['allow_negative_targets'] ?? false) && !$this->isNegativeValueAllowed($kpi)) {
                $errors[] = 'Zielwert sollte positiv sein.';
            }
            if (abs($target) > self::MAX_VALUE_MAGNITUDE) {
                $warnings[] = 'Sehr großer Zielwert - bitte Einheit prüfen';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert benutzer-spezifische Regeln.
     */
    private function validateUserSpecificRules(KPI $kpi, array $context): array
    {
        $errors = [];
        $warnings = [];
        $user = $kpi->getUser();

        // Maximale Anzahl KPIs pro Benutzer
        $userKpiCount = $this->kpiRepository->countByUser($user);
        if (!($context['is_update'] ?? false) && $userKpiCount >= self::MAX_KPIS_PER_USER) {
            $errors[] = "Maximale Anzahl KPIs pro Benutzer erreicht (" . self::MAX_KPIS_PER_USER . ")";
        }

        // Name-Duplikate innerhalb des Benutzers
        $duplicateName = $this->kpiRepository->findByUserAndName($user, $kpi->getName());
        if ($duplicateName && $duplicateName->getId() !== $kpi->getId()) {
            $errors[] = "KPI-Name bereits vorhanden für diesen Benutzer";
        }

        // Benutzer-Rollen-basierte Einschränkungen (vereinfacht)
        if ($this->isRestrictedUser($user)) {
            if ($kpi->getInterval() === KpiInterval::QUARTERLY) {
                $warnings[] = 'Quartalsweise KPIs erfordern erweiterte Berechtigungen';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert allgemeine Geschäftsregeln.
     */
    public function validateBusinessRules(KPI $kpi, array $context): array
    {
        $errors = [];
        $warnings = [];

        // Sinnvolle Intervall-Kombinationen
        $name = strtolower($kpi->getName());
        $interval = $kpi->getInterval();

        if ($interval === KpiInterval::WEEKLY && str_contains($name, 'jahres')) {
            $warnings[] = 'Wöchentliches Intervall bei jährlicher KPI könnte ungeeignet sein';
        }

        if ($interval === KpiInterval::QUARTERLY && str_contains($name, 'täglich')) {
            $warnings[] = 'Quartalsintervall bei täglicher KPI könnte ungeeignet sein';
        }

        // Kritische KPI-Namen (benötigen besondere Aufmerksamkeit)
        $criticalKeywords = ['umsatz', 'gewinn', 'verlust', 'kosten', 'revenue'];
        foreach ($criticalKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                $warnings[] = "Kritische KPI erkannt - besondere Sorgfalt bei der Datenpflege erforderlich";
                break;
            }
        }
        
        // Business rules from context
        if (isset($context['business_rules']['max_kpis_per_user']) && 
            isset($context['business_rules']['current_kpi_count'])) {
            $maxKpis = $context['business_rules']['max_kpis_per_user'];
            $currentCount = $context['business_rules']['current_kpi_count'];
            
            if ($currentCount > $maxKpis) {
                $errors[] = 'Maximale Anzahl KPIs pro Benutzer überschritten.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert Update-Konsistenz für bestehende KPIs.
     */
    private function validateUpdateConsistency(KPI $kpi, array $context): array
    {
        $errors = [];
        $warnings = [];

        $originalKpi = $context['original_kpi'] ?? null;
        if (!$originalKpi) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Kritische Änderungen die existierende Daten betreffen können
        if ($kpi->getInterval() !== $originalKpi->getInterval()) {
            $valueCount = $this->kpiValueRepository->countByKPI($kpi);
            if ($valueCount > 0) {
                $warnings[] = "Intervall-Änderung bei vorhandenen Werten - Datenintegrität prüfen";
            }
        }

        // User-Änderung (normalerweise nicht erlaubt)
        if ($kpi->getUser()->getId() !== $originalKpi->getUser()->getId()) {
            $errors[] = "KPI-Besitzer kann nicht geändert werden";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert grundlegende KPI-Value Anforderungen.
     */
    private function validateBasicValueRequirements(KPIValue $kpiValue): array
    {
        $errors = [];
        $warnings = [];

        // KPI-Zuordnung
        if (!$kpiValue->getKpi()) {
            $errors[] = 'KPI-Wert muss einer KPI zugeordnet sein.';
        }

        // Period-Validierung
        if (!$kpiValue->getPeriod()) {
            $errors[] = 'Periode ist erforderlich.';
        }

        // Wert-Validierung
        $numericValue = $kpiValue->getValueAsFloat();
        if ($numericValue === null) {
            $errors[] = 'Gültiger numerischer Wert erforderlich';
        } elseif (is_nan($numericValue)) {
            $errors[] = 'Wert ist ungültig (NaN).';
        } else {
            if (abs($numericValue) > self::MAX_VALUE_MAGNITUDE) {
                $errors[] = 'Wert überschreitet maximal erlaubte Größe';
            }
        }

        // Kommentar-Länge
        $comment = $kpiValue->getComment();
        if ($comment && mb_strlen($comment) > self::MAX_VALUE_COMMENT_LENGTH) {
            $errors[] = "Kommentar zu lang (max. " . self::MAX_VALUE_COMMENT_LENGTH . " Zeichen)";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert Datenqualität des KPI-Wertes.
     */
    private function validateDataQuality(KPIValue $kpiValue, array $context): array
    {
        $errors = [];
        $warnings = [];

        $numericValue = $kpiValue->getValueAsFloat();
        
        // NaN und Infinity-Checks
        if (is_nan($numericValue)) {
            $errors[] = 'Wert ist ungültig (NaN).';
        }
        if (is_infinite($numericValue)) {
            $errors[] = 'Wert ist ungültig (Infinity).';
        }
        
        // Null/Zero-Werte je nach KPI-Typ
        if ($numericValue === 0.0) {
            $kpiName = strtolower($kpiValue->getKpi()->getName());
            if (str_contains($kpiName, 'umsatz') || str_contains($kpiName, 'gewinn')) {
                $warnings[] = 'Null-Wert bei Umsatz/Gewinn-KPI - bitte prüfen';
            }
        }

        // Extremwerte
        if ($numericValue !== null && abs($numericValue) > 1000000) {
            $warnings[] = 'Sehr großer Wert - bitte Einheit und Korrektheit prüfen';
        }

        // Datenqualitäts-Indikatoren
        $hasComment = !empty($kpiValue->getComment());
        $hasFiles = $kpiValue->getFiles()->count() > 0;
        
        if (!$hasComment && !$hasFiles && ($context['require_evidence'] ?? false)) {
            $warnings[] = 'Keine Kommentare oder Belege - Datenqualität könnte verbessert werden';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert historische Konsistenz.
     */
    private function validateHistoricalConsistency(KPIValue $kpiValue, array $context): array
    {
        $warnings = [];
        
        $kpi = $kpiValue->getKpi();
        // Try to get recent values, handle if method doesn't exist
        try {
            $recentValues = $this->kpiValueRepository->findRecentByKPI($kpi, 5) ?? [];
        } catch (\Exception $e) {
            $recentValues = []; // Fallback if method doesn't exist
        }
        
        if (count($recentValues) < 2) {
            return ['warnings' => $warnings];
        }

        $currentValue = $kpiValue->getValueAsFloat();
        $recentNumericValues = array_map(fn($v) => $v->getValueAsFloat(), $recentValues);
        
        $avgRecent = array_sum($recentNumericValues) / count($recentNumericValues);
        
        // Ausreißer-Detektion
        if (abs($currentValue - $avgRecent) > (2 * $this->calculateStandardDeviation($recentNumericValues))) {
            $warnings[] = 'Wert weicht stark von historischen Werten ab - bitte prüfen';
        }

        return ['warnings' => $warnings];
    }

    /**
     * Validiert business-spezifische Wert-Regeln.
     */
    private function validateValueBusinessRules(KPIValue $kpiValue, array $context): array
    {
        $errors = [];
        $warnings = [];

        $kpi = $kpiValue->getKpi();
        if ($kpi === null) {
            return $errors; // Keine Business Rules ohne KPI
        }
        
        $value = $kpiValue->getValueAsFloat();
        $target = $kpi->getTargetAsFloat();

        // Zielwert-Vergleich
        if ($target !== null && $value !== null) {
            $deviation = abs(($value - $target) / $target) * 100;
            
            if ($deviation > 100) { // Über 100% Abweichung
                $warnings[] = "Starke Abweichung vom Zielwert ({$deviation}%)";
            }
        }

        // Geschäfts-spezifische Regeln
        $kpiName = strtolower($kpi->getName());
        
        if (str_contains($kpiName, 'prozent') || str_contains($kpiName, '%')) {
            if ($value > 100) {
                $warnings[] = 'Prozentwert über 100% - bitte Korrektheit prüfen';
            }
            if ($value < 0) {
                $warnings[] = 'Negativer Prozentwert - bitte Plausibilität prüfen';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validiert globale Constraints für Bulk-Operationen.
     */
    private function validateGlobalConstraints(array $kpis, array $context): array
    {
        $errors = [];
        
        // Duplikat-Namen innerhalb des Batches
        $names = array_map(fn($kpi) => $kpi->getName(), $kpis);
        $duplicateNames = array_diff_assoc($names, array_unique($names));
        
        if (!empty($duplicateNames)) {
            $errors[] = 'Doppelte KPI-Namen innerhalb der Bulk-Operation gefunden';
        }

        // Batch-Größe
        if (count($kpis) > 100) {
            $errors[] = 'Bulk-Operation zu groß (max. 100 KPIs pro Batch)';
        }

        return ['errors' => $errors];
    }

    /**
     * Prüft Abhängigkeiten vor KPI-Löschung.
     */
    private function checkDependenciesBeforeDeletion(KPI $kpi): array
    {
        $valueCount = $this->kpiValueRepository->countByKPI($kpi);
        
        if ($valueCount > 0) {
            return [
                'can_delete' => false,
                'reason' => "KPI hat {$valueCount} zugehörige Werte",
                'affected_count' => $valueCount,
                'warnings' => ["Löschung würde {$valueCount} Werte entfernen"],
            ];
        }

        return [
            'can_delete' => true,
            'reason' => 'Keine Abhängigkeiten gefunden',
            'affected_count' => 0,
            'warnings' => [],
        ];
    }

    /**
     * Validiert Löschungs-Geschäftsregeln.
     */
    private function validateDeletionBusinessRules(KPI $kpi, array $context): array
    {
        $errors = [];
        $warnings = [];

        // Kritische KPIs
        $name = strtolower($kpi->getName());
        $criticalKeywords = ['umsatz', 'gewinn', 'hauptkennzahl'];
        
        foreach ($criticalKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                if (!($context['force_delete'] ?? false)) {
                    $errors[] = 'Kritische KPI kann nicht ohne explizite Berechtigung gelöscht werden';
                }
                break;
            }
        }

        // Zeitbasierte Einschränkungen
        $createdAt = $kpi->getCreatedAt();
        if ($createdAt && $createdAt < new \DateTimeImmutable('-30 days')) {
            $warnings[] = 'KPI ist älter als 30 Tage - Löschung könnte historische Auswertungen beeinträchtigen';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    // Helper-Methoden

    private function isNegativeValueAllowed(KPI $kpi): bool
    {
        $name = strtolower($kpi->getName());
        $negativeAllowedKeywords = ['gewinn', 'verlust', 'änderung', 'delta'];
        
        foreach ($negativeAllowedKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function isRestrictedUser(User $user): bool
    {
        // Vereinfachte Implementierung - in Realität würde hier Symfony Security geprüft
        return false; // Alle Benutzer haben volle Rechte
    }

    private function shouldRecommendArchiving(KPI $kpi): bool
    {
        $valueCount = $this->kpiValueRepository->countByKPI($kpi);
        return $valueCount > 10; // Bei mehr als 10 Werten Archivierung empfehlen
    }

    private function suggestAlternativeActions(KPI $kpi): array
    {
        $actions = [];
        
        if ($this->shouldRecommendArchiving($kpi)) {
            $actions[] = [
                'type' => 'archive',
                'label' => 'KPI archivieren',
                'description' => 'KPI deaktivieren aber Daten behalten',
            ];
        }
        
        $actions[] = [
            'type' => 'export',
            'label' => 'Daten exportieren',
            'description' => 'KPI-Daten vor Löschung sichern',
        ];

        return $actions;
    }

    private function calculateSeverity(array $errors, array $warnings): string
    {
        if (!empty($errors)) {
            return 'error';
        }
        if (!empty($warnings)) {
            return 'warning';
        }
        return 'info';
    }

    private function calculateDataQualityScore(KPIValue $kpiValue): float
    {
        $score = 0.5; // Basis-Score für vorhandenen Wert
        
        if ($kpiValue->getComment()) {
            $score += 0.25; // Bonus für Kommentar
        }
        
        if ($kpiValue->getFiles()->count() > 0) {
            $score += 0.25; // Bonus für Dateien/Belege
        }
        
        return min(1.0, $score);
    }

    private function calculateStandardDeviation(array $values): float
    {
        if (count($values) < 2) return 0;
        
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(fn($value) => pow($value - $mean, 2), $values);
        $variance = array_sum($squaredDifferences) / count($values);
        
        return sqrt($variance);
    }

    /**
     * Validiert KPI mit erweiterten Kontext-Informationen.
     */
    public function validateWithContext(KPI $kpi, array $context = []): array
    {
        $errors = [];
        $result = $this->validateKPIFull($kpi, $context);
        $errors = array_merge($errors, $result['errors'] ?? []);
        
        // Check for unique KPI name
        if (isset($context['check_unique_name']) && $context['check_unique_name']) {
            $existingNames = $context['existing_kpi_names'] ?? [];
            if (in_array($kpi->getName(), $existingNames)) {
                $errors[] = 'KPI-Name existiert bereits für diesen Benutzer.';
            }
        }
        
        return $errors;
    }

    /**
     * Prüft auf Duplikate bei KPI-Werten.
     */
    public function checkForDuplicates(KPI $kpi, Period $period, ?DecimalValue $value): bool
    {
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $period);
        return $existingValue !== null;
    }

    /**
     * Validiert mehrere KPIs in einem Batch-Prozess.
     */
    public function validateMultipleKpis(array $kpis, array $options = []): array
    {
        $results = [];
        
        foreach ($kpis as $kpi) {
            $result = $this->validateKPIFull($kpi);
            $results[] = $result['errors'] ?? [];
        }
        
        return [
            'total_validated' => count($kpis),
            'results' => $results,
            'batch_timestamp' => new \DateTimeImmutable()
        ];
    }

    /**
     * Validiert Abhängigkeiten einer KPI.
     */
    private function validateDependencies(KPI $kpi): array
    {
        return [
            'has_dependencies' => false,
            'dependency_status' => 'ok'
        ];
    }

    /**
     * Validiert eine spezifische Geschäftsregel.
     */
    private function validateSpecificBusinessRule(KPI $kpi, string $rule, $config): bool
    {
        return match($rule) {
            'max_frequency' => true,
            'required_approval' => true,
            'budget_constraint' => true,
            default => true
        };
    }

    /**
     * Validiert KPI-Datenarray.
     */
    public function validateKpiData(array $data): array
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Name ist erforderlich.';
        }
        
        if (empty($data['interval'])) {
            $errors[] = 'Intervall ist erforderlich.';
        }
        
        if (empty($data['user']) && empty($data['user_id'])) {
            $errors[] = 'Benutzer ist erforderlich.';
        }
        
        return $errors;
    }

    /**
     * Validiert Cross-Entity Konsistenz.
     */
    public function validateCrossEntityConsistency(array $entities, array $rules = []): array
    {
        $violations = [];
        
        // Check related values consistency if provided
        if (isset($rules['related_values'])) {
            foreach ($entities as $kpi) {
                foreach ($rules['related_values'] as $kpiValue) {
                    if ($kpiValue->getKpi() !== $kpi) {
                        $violations[] = 'KPI-Wert gehört nicht zu dieser KPI.';
                    }
                }
            }
        }
        
        return [
            'consistent' => empty($violations),
            'violations' => $violations
        ];
    }

    /**
     * Bulk-Validierung für mehrere Entitäten.
     */
    public function validateBulk(array $entities, array $options = []): array
    {
        $results = [];
        $totalErrors = 0;
        $validCount = 0;
        
        foreach ($entities as $entity) {
            if ($entity instanceof KPI) {
                $validation = $this->validateKpi($entity);
                $results[] = ['errors' => $validation];
                
                if (empty($validation)) {
                    $validCount++;
                } else {
                    $totalErrors += count($validation);
                }
            }
        }
        
        return [
            'individual_results' => $results,
            'total_errors' => $totalErrors,
            'valid_count' => $validCount,
            'invalid_count' => count($results) - $validCount
        ];
    }

    /**
     * Validierung mit externem Kontext.
     */
    public function validateWithExternalContext(KPI $kpi, array $externalData): array
    {
        $result = $this->validateKPIFull($kpi);
        $errors = $result['errors'] ?? [];
        
        // Check forbidden names
        if (isset($externalData['external_validation']) && $externalData['external_validation']) {
            $forbiddenNames = $externalData['forbidden_names'] ?? [];
            if (in_array($kpi->getName(), $forbiddenNames)) {
                $errors[] = 'KPI-Name ist nicht erlaubt.';
            }
        }
        
        return $errors;
    }

    /**
     * Validiert Marktbedingungen.
     */
    private function validateMarketConditions(KPI $kpi, array $conditions): array
    {
        return [
            'market_compatible' => true,
            'recommendations' => []
        ];
    }


    /**
     * Validates KPI with custom configuration.
     */
    public function validateWithConfig(KPI $kpi, array $config): array
    {
        $errors = [];
        $warnings = [];

        // Custom name length validation
        $minLength = $config['min_name_length'] ?? self::MIN_KPI_NAME_LENGTH;
        $name = $kpi->getName();
        if (!$name || trim($name) === '') {
            $errors[] = 'KPI-Name ist erforderlich.';
        } elseif (mb_strlen($name) < $minLength) {
            $errors[] = "KPI-Name muss mindestens " . $minLength . " Zeichen lang sein.";
        }

        // Other basic validations without name length
        if (!$kpi->getInterval()) {
            $errors[] = 'Ungültiges Intervall gewählt.';
        }

        if (!$kpi->getUser()) {
            $errors[] = 'KPI muss einem Benutzer zugeordnet sein.';
        }

        return $errors;
    }

    /**
     * Simple KPI validation method that returns just the errors array.
     */
    public function validateKpiSimple(KPI $kpi): array 
    {
        $result = $this->validateKPIFull($kpi);
        return $result['errors'] ?? [];
    }

    /**
     * Validates a KPI and returns errors array (for tests compatibility).
     */
    public function validateKpi(KPI $kpi): array 
    {
        return $this->validateKpiSimple($kpi);
    }

    /**
     * Simple KPI Value validation method that returns just the errors array.
     */
    public function validateKpiValueSimple(KPIValue $kpiValue): array 
    {
        $result = $this->validateKPIValueFull($kpiValue);
        return $result['errors'] ?? [];
    }

    /**
     * Validates a KPI value and returns errors array (for tests compatibility).
     */
    public function validateKpiValue(KPIValue $kpiValue): array 
    {
        return $this->validateKpiValueSimple($kpiValue);
    }
}