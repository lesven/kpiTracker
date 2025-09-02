<?php

namespace App\Domain\Service;

use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;

/**
 * Domain Service für erweiterte Duplikat-Erkennung bei KPI-Werten.
 *
 * Implementiert komplexe Geschäftsregeln für:
 * - Zeitraum-basierte Duplikat-Prüfung
 * - Fuzzy-Matching für ähnliche Zeiträume
 * - Wert-basierte Duplikat-Erkennung
 * - Kontextuelle Überschreibungs-Logik
 * - Audit-Trail für Duplikat-Operationen
 */
class KPIDuplicateDetectionDomainService
{
    /**
     * Toleranz-Schwellwerte für Fuzzy-Matching.
     */
    private const FUZZY_PERIOD_TOLERANCE_DAYS = 3;
    private const VALUE_SIMILARITY_THRESHOLD = 0.01; // 1% Toleranz
    private const MAX_CONFLICTS_TO_RETURN = 10;

    public function __construct(
        private KPIValueRepository $kpiValueRepository,
    ) {
    }

    /**
     * Prüft ob ein KPI-Wert als Duplikat betrachtet werden sollte.
     *
     * @param KPIValue $newValue Der neue zu prüfende Wert
     * @param array $options Konfigurationsoptionen für Duplikat-Erkennung
     * @return array Duplikat-Analyse Ergebnis
     */
    public function checkForDuplicate(KPIValue $newValue, array $options = []): array
    {
        $config = $this->mergeDetectionConfig($options);
        
        $exactDuplicate = $this->findExactDuplicate($newValue);
        $fuzzyMatches = $config['enable_fuzzy_matching'] ? $this->findFuzzyMatches($newValue, $config) : [];
        $valueSimilarities = $config['check_value_similarity'] ? $this->findValueSimilarities($newValue, $config) : [];

        $hasDuplicate = $exactDuplicate || !empty($fuzzyMatches) || !empty($valueSimilarities);
        
        return [
            'has_duplicate' => $hasDuplicate,
            'duplicate_type' => $this->determineDuplicateType($exactDuplicate, $fuzzyMatches, $valueSimilarities),
            'exact_duplicate' => $exactDuplicate,
            'fuzzy_matches' => $fuzzyMatches,
            'value_similarities' => $valueSimilarities,
            'recommendation' => $this->generateRecommendation($exactDuplicate, $fuzzyMatches, $valueSimilarities),
            'override_options' => $this->generateOverrideOptions($exactDuplicate, $fuzzyMatches),
        ];
    }

    /**
     * Prüft ob ein bestehender Wert durch einen neuen überschrieben werden darf.
     *
     * @param KPIValue $existingValue Der bestehende Wert
     * @param KPIValue $newValue Der neue Wert
     * @param array $businessRules Geschäftsregeln für Überschreibung
     * @return array Überschreibungs-Analyse
     */
    public function canOverrideValue(KPIValue $existingValue, KPIValue $newValue, array $businessRules = []): array
    {
        $reasons = [];
        $canOverride = true;
        $confidence = 1.0;

        // Zeitbasierte Prüfungen
        $timeAnalysis = $this->analyzeTimeBasedOverride($existingValue, $newValue);
        if (!$timeAnalysis['allowed']) {
            $canOverride = false;
            $reasons[] = $timeAnalysis['reason'];
        }

        // Wert-Änderungs-Analyse
        $valueAnalysis = $this->analyzeValueChange($existingValue, $newValue, $businessRules);
        if ($valueAnalysis['requires_confirmation']) {
            $confidence *= 0.5;
            $reasons[] = $valueAnalysis['reason'];
        }

        // Benutzer-Berechtigung prüfen
        $authAnalysis = $this->analyzeUserPermission($existingValue, $newValue);
        if (!$authAnalysis['allowed']) {
            $canOverride = false;
            $reasons[] = $authAnalysis['reason'];
        }

        // Datenqualitäts-Prüfung
        $qualityAnalysis = $this->analyzeDataQuality($existingValue, $newValue);
        $confidence *= $qualityAnalysis['quality_factor'];

        return [
            'can_override' => $canOverride,
            'confidence' => $confidence,
            'reasons' => $reasons,
            'recommendations' => $this->generateOverrideRecommendations($timeAnalysis, $valueAnalysis, $authAnalysis),
            'audit_info' => [
                'existing_created' => $existingValue->getCreatedAt(),
                'existing_updated' => $existingValue->getUpdatedAt(),
                'value_change_magnitude' => $valueAnalysis['change_magnitude'],
                'quality_score' => $qualityAnalysis['quality_score'],
            ],
        ];
    }

    /**
     * Findet alle konfliktierenden Werte für einen KPI-Zeitraum.
     *
     * @param KPI $kpi Die KPI
     * @param Period $period Der Zeitraum
     * @param bool $includeFuzzy Auch Fuzzy-Matches einschließen
     * @return array Liste aller konfliktierenden Werte
     */
    public function findConflictingValues(KPI $kpi, Period $period, bool $includeFuzzy = false): array
    {
        $conflicts = [];

        // Exakte Matches
        $exactMatch = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $period);
        if ($exactMatch) {
            $conflicts[] = [
                'value' => $exactMatch,
                'conflict_type' => 'exact_period',
                'similarity_score' => 1.0,
            ];
        }

        if ($includeFuzzy) {
            $fuzzyMatches = $this->findFuzzyPeriodMatches($kpi, $period);
            foreach ($fuzzyMatches as $match) {
                $conflicts[] = [
                    'value' => $match['value'],
                    'conflict_type' => 'fuzzy_period',
                    'similarity_score' => $match['similarity_score'],
                    'period_difference_days' => $match['period_difference_days'],
                ];
            }
        }

        // Nach Ähnlichkeits-Score sortieren
        usort($conflicts, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return array_slice($conflicts, 0, self::MAX_CONFLICTS_TO_RETURN);
    }

    /**
     * Analysiert Duplikat-Muster für eine KPI (für Datenqualitäts-Insights).
     *
     * @param KPI $kpi Die zu analysierende KPI
     * @param int $timeframeDays Analysezeitraum in Tagen
     * @return array Duplikat-Muster Analyse
     */
    public function analyzeDuplicatePatterns(KPI $kpi, int $timeframeDays = 90): array
    {
        $cutoffDate = new \DateTimeImmutable("-{$timeframeDays} days");
        $recentValues = $this->kpiValueRepository->findByKPI($kpi); // TODO: Filter by date
        
        if (count($recentValues) < 2) {
            return ['status' => 'insufficient_data'];
        }

        $patterns = [
            'total_values' => count($recentValues),
            'potential_duplicates' => 0,
            'exact_duplicates' => 0,
            'fuzzy_matches' => 0,
            'value_duplicates' => 0,
            'duplicate_rate' => 0,
            'recommendations' => [],
        ];

        foreach ($recentValues as $value) {
            $duplicateCheck = $this->checkForDuplicate($value, ['enable_fuzzy_matching' => true]);
            
            if ($duplicateCheck['has_duplicate']) {
                $patterns['potential_duplicates']++;
                
                if ($duplicateCheck['exact_duplicate']) {
                    $patterns['exact_duplicates']++;
                }
                
                if (!empty($duplicateCheck['fuzzy_matches'])) {
                    $patterns['fuzzy_matches']++;
                }
                
                if (!empty($duplicateCheck['value_similarities'])) {
                    $patterns['value_duplicates']++;
                }
            }
        }

        $patterns['duplicate_rate'] = ($patterns['potential_duplicates'] / $patterns['total_values']) * 100;
        $patterns['recommendations'] = $this->generateDataQualityRecommendations($patterns);

        return $patterns;
    }

    /**
     * Findet exakte Duplikate basierend auf KPI und Zeitraum.
     */
    private function findExactDuplicate(KPIValue $newValue): ?KPIValue
    {
        return $this->kpiValueRepository->findByKpiAndPeriod(
            $newValue->getKpi(), 
            $newValue->getPeriod()
        );
    }

    /**
     * Findet ähnliche Zeiträume mit konfigurierbarer Toleranz.
     */
    private function findFuzzyMatches(KPIValue $newValue, array $config): array
    {
        $kpi = $newValue->getKpi();
        $period = $newValue->getPeriod();
        
        return $this->findFuzzyPeriodMatches($kpi, $period);
    }

    /**
     * Findet Fuzzy-Matches basierend auf Zeitraum-Ähnlichkeit.
     */
    private function findFuzzyPeriodMatches(KPI $kpi, Period $period): array
    {
        $allValues = $this->kpiValueRepository->findByKPI($kpi);
        $matches = [];
        
        foreach ($allValues as $existingValue) {
            $existingPeriod = $existingValue->getPeriod();
            
            if (!$existingPeriod || $existingPeriod->value() === $period->value()) {
                continue; // Überspringen - exakte Matches wurden bereits geprüft
            }
            
            $similarity = $this->calculatePeriodSimilarity($period, $existingPeriod);
            
            if ($similarity > 0.7) { // 70% Ähnlichkeit
                $matches[] = [
                    'value' => $existingValue,
                    'similarity_score' => $similarity,
                    'period_difference_days' => $this->calculatePeriodDifferenceDays($period, $existingPeriod),
                ];
            }
        }
        
        // Nach Ähnlichkeit sortieren
        usort($matches, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);
        
        return $matches;
    }

    /**
     * Findet Werte mit ähnlichen numerischen Werten.
     */
    private function findValueSimilarities(KPIValue $newValue, array $config): array
    {
        $kpi = $newValue->getKpi();
        $newNumericValue = $newValue->getValueAsFloat();
        $threshold = $config['value_similarity_threshold'] ?? self::VALUE_SIMILARITY_THRESHOLD;
        
        $recentValues = $this->kpiValueRepository->findByKPI($kpi); // TODO: Limit to recent values
        $similarities = [];
        
        foreach ($recentValues as $existingValue) {
            $existingNumericValue = $existingValue->getValueAsFloat();
            
            if (abs($existingNumericValue - $newNumericValue) / max(abs($newNumericValue), 1) <= $threshold) {
                $similarities[] = [
                    'value' => $existingValue,
                    'numeric_difference' => abs($existingNumericValue - $newNumericValue),
                    'percentage_difference' => abs(($existingNumericValue - $newNumericValue) / max(abs($newNumericValue), 1)) * 100,
                ];
            }
        }
        
        return $similarities;
    }

    /**
     * Bestimmt den Typ des Duplikats.
     */
    private function determineDuplicateType(?KPIValue $exact, array $fuzzy, array $values): string
    {
        if ($exact) {
            return 'exact';
        }
        
        if (!empty($fuzzy)) {
            return 'fuzzy_period';
        }
        
        if (!empty($values)) {
            return 'similar_value';
        }
        
        return 'none';
    }

    /**
     * Generiert Empfehlungen basierend auf Duplikat-Analyse.
     */
    private function generateRecommendation(?KPIValue $exact, array $fuzzy, array $values): string
    {
        if ($exact) {
            return 'Ein exaktes Duplikat wurde gefunden. Überschreibung oder Aktualisierung erforderlich.';
        }
        
        if (!empty($fuzzy)) {
            return 'Ähnliche Zeiträume gefunden. Bitte prüfen Sie ob dies der korrekte Zeitraum ist.';
        }
        
        if (!empty($values)) {
            return 'Ähnliche Werte in anderen Zeiträumen gefunden. Bitte Wert-Genauigkeit prüfen.';
        }
        
        return 'Keine Duplikate gefunden. Wert kann gespeichert werden.';
    }

    /**
     * Generiert Überschreibungsoptionen.
     */
    private function generateOverrideOptions(?KPIValue $exact, array $fuzzy): array
    {
        $options = [];
        
        if ($exact) {
            $options[] = [
                'type' => 'replace',
                'label' => 'Bestehenden Wert ersetzen',
                'description' => 'Der neue Wert überschreibt den bestehenden Wert komplett.',
            ];
            
            $options[] = [
                'type' => 'update',
                'label' => 'Bestehenden Wert aktualisieren',
                'description' => 'Nur geänderte Felder werden aktualisiert, Metadaten bleiben erhalten.',
            ];
        }
        
        if (!empty($fuzzy)) {
            $options[] = [
                'type' => 'new_period',
                'label' => 'Als neuen Zeitraum speichern',
                'description' => 'Wert als separaten Eintrag für den spezifizierten Zeitraum speichern.',
            ];
        }
        
        return $options;
    }

    /**
     * Führt Standard-Konfiguration mit benutzerdefinierten zusammen.
     */
    private function mergeDetectionConfig(array $config): array
    {
        return array_merge([
            'enable_fuzzy_matching' => true,
            'fuzzy_tolerance_days' => self::FUZZY_PERIOD_TOLERANCE_DAYS,
            'check_value_similarity' => true,
            'value_similarity_threshold' => self::VALUE_SIMILARITY_THRESHOLD,
        ], $config);
    }

    /**
     * Analysiert zeitbasierte Überschreibungsregeln.
     */
    private function analyzeTimeBasedOverride(KPIValue $existing, KPIValue $new): array
    {
        $existingCreated = $existing->getCreatedAt();
        $now = new \DateTimeImmutable();
        
        // Regel: Werte älter als 24h können nicht überschrieben werden ohne Berechtigung
        $hoursSinceCreation = $now->diff($existingCreated)->h + ($now->diff($existingCreated)->days * 24);
        
        if ($hoursSinceCreation > 24) {
            return [
                'allowed' => false,
                'reason' => 'Wert ist älter als 24 Stunden und benötigt spezielle Berechtigung',
                'hours_since_creation' => $hoursSinceCreation,
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => 'Zeitbasierte Überschreibung erlaubt',
            'hours_since_creation' => $hoursSinceCreation,
        ];
    }

    /**
     * Analysiert Wert-Änderungen für Plausibilität.
     */
    private function analyzeValueChange(KPIValue $existing, KPIValue $new, array $businessRules): array
    {
        $existingValue = $existing->getValueAsFloat();
        $newValue = $new->getValueAsFloat();
        
        $changeMagnitude = abs($newValue - $existingValue);
        $percentageChange = $existingValue != 0 ? ($changeMagnitude / abs($existingValue)) * 100 : 0;
        
        $suspiciousChangeThreshold = $businessRules['suspicious_change_threshold'] ?? 50; // 50%
        
        if ($percentageChange > $suspiciousChangeThreshold) {
            return [
                'requires_confirmation' => true,
                'reason' => "Große Wertänderung ({$percentageChange}%) erfordert Bestätigung",
                'change_magnitude' => $changeMagnitude,
                'percentage_change' => $percentageChange,
            ];
        }
        
        return [
            'requires_confirmation' => false,
            'reason' => 'Wertänderung liegt im normalen Bereich',
            'change_magnitude' => $changeMagnitude,
            'percentage_change' => $percentageChange,
        ];
    }

    /**
     * Analysiert Benutzer-Berechtigung für Überschreibung.
     */
    private function analyzeUserPermission(KPIValue $existing, KPIValue $new): array
    {
        // Vereinfachte Implementierung - in Realität würde hier Symfony Security verwendet
        $existingKpi = $existing->getKpi();
        $newKpi = $new->getKpi();
        
        if ($existingKpi->getId() !== $newKpi->getId()) {
            return [
                'allowed' => false,
                'reason' => 'Wert gehört zu einer anderen KPI',
            ];
        }
        
        // TODO: Prüfung ob aktueller Benutzer Berechtigung hat
        
        return [
            'allowed' => true,
            'reason' => 'Benutzer hat Berechtigung zur Überschreibung',
        ];
    }

    /**
     * Analysiert Datenqualität von bestehenden vs. neuen Werten.
     */
    private function analyzeDataQuality(KPIValue $existing, KPIValue $new): array
    {
        $qualityScore = 1.0;
        
        // Hat der neue Wert zusätzliche Metadaten?
        if ($new->getComment() && !$existing->getComment()) {
            $qualityScore *= 1.1; // 10% Bonus für Kommentar
        }
        
        // Hat der neue Wert Dateien/Belege?
        $newHasFiles = $new->getFiles()->count() > 0;
        $existingHasFiles = $existing->getFiles()->count() > 0;
        
        if ($newHasFiles && !$existingHasFiles) {
            $qualityScore *= 1.2; // 20% Bonus für Dateien
        }
        
        return [
            'quality_factor' => min(1.0, $qualityScore),
            'quality_score' => $qualityScore,
            'has_improvement' => $qualityScore > 1.0,
        ];
    }

    /**
     * Generiert Empfehlungen für Überschreibung.
     */
    private function generateOverrideRecommendations(array $timeAnalysis, array $valueAnalysis, array $authAnalysis): array
    {
        $recommendations = [];
        
        if (!$timeAnalysis['allowed']) {
            $recommendations[] = 'Kontaktieren Sie einen Administrator für historische Wertänderungen';
        }
        
        if ($valueAnalysis['requires_confirmation']) {
            $recommendations[] = 'Fügen Sie einen Kommentar hinzu um die große Wertänderung zu erklären';
        }
        
        if ($authAnalysis['allowed']) {
            $recommendations[] = 'Überschreibung ist technisch möglich';
        }
        
        return $recommendations;
    }

    /**
     * Berechnet Ähnlichkeit zwischen zwei Zeiträumen.
     */
    private function calculatePeriodSimilarity(Period $period1, Period $period2): float
    {
        // Vereinfachte Implementierung basierend auf String-Ähnlichkeit
        $str1 = $period1->value();
        $str2 = $period2->value();
        
        // Levenshtein-Ähnlichkeit
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Berechnet Tage-Differenz zwischen Zeiträumen (approximativ).
     */
    private function calculatePeriodDifferenceDays(Period $period1, Period $period2): int
    {
        // Vereinfachte Implementierung - würde in Realität komplexeres Parsing benötigen
        return abs(strlen($period1->value()) - strlen($period2->value()));
    }

    /**
     * Generiert Empfehlungen zur Datenqualitäts-Verbesserung.
     */
    private function generateDataQualityRecommendations(array $patterns): array
    {
        $recommendations = [];
        
        if ($patterns['duplicate_rate'] > 20) {
            $recommendations[] = 'Hohe Duplikatrate erkannt. Prüfen Sie Ihre Eingabeprozesse';
        }
        
        if ($patterns['fuzzy_matches'] > 5) {
            $recommendations[] = 'Viele ähnliche Zeiträume gefunden. Standardisieren Sie Zeitraum-Eingaben';
        }
        
        if ($patterns['value_duplicates'] > 3) {
            $recommendations[] = 'Ähnliche Werte in verschiedenen Zeiträumen. Prüfen Sie Wert-Genauigkeit';
        }
        
        return $recommendations;
    }
}