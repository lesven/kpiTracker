<?php

namespace App\Service;

use App\Domain\Service\KPIDuplicateDetectionDomainService;
use App\Domain\Service\KPIReminderDomainService;
use App\Domain\Service\KPIStatisticsDomainService;
use App\Domain\Service\KPIStatusDomainService;
use App\Domain\Service\KPITrendDomainService;
use App\Domain\Service\KPIValidationDomainService;
use App\Domain\ValueObject\KPIStatistics;
use App\Domain\ValueObject\KPIStatus;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * KPI-Aggregat: Koordiniert KPI-Operationen mit Domain Services.
 *
 * Diese Klasse wurde von einer monolithischen Business-Logic-Implementierung
 * zu einem Application Service refactored, der spezialisierte Domain Services
 * koordiniert. Dies folgt Domain-Driven Design Prinzipien und verbessert
 * Testbarkeit, Wartbarkeit und Erweiterbarkeit.
 *
 * Verantwortlichkeiten:
 * - Koordination zwischen Domain Services
 * - Transaktions-Management
 * - Event-Publishing nach Operationen
 * - Datenpersistierung
 * - Legacy-Kompatibilität für bestehende Services
 */
class KPIAggregate
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KPIValueRepository $kpiValueRepository,
        private FileUploadService $fileUploadService,
        private KPIStatusDomainService $statusService,
        private KPIStatisticsDomainService $statisticsService,
        private KPITrendDomainService $trendService,
        private KPIValidationDomainService $validationService,
        private KPIDuplicateDetectionDomainService $duplicateDetectionService,
        private KPIReminderDomainService $reminderService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Ermittelt den aktuellen Status einer KPI über den Status Domain Service.
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return string Status-Code: 'green', 'yellow' oder 'red'
     */
    public function getKpiStatus(KPI $kpi): string
    {
        $statusVO = $this->statusService->calculateStatus($kpi);
        return $statusVO->toString();
    }

    /**
     * Ermittelt den KPI-Status als Value Object für erweiterte Funktionen.
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return KPIStatus Status als Value Object mit erweiterten Methoden
     */
    public function getKpiStatusValueObject(KPI $kpi): KPIStatus
    {
        return $this->statusService->calculateStatus($kpi);
    }

    /**
     * Prüft, ob für die KPI bereits ein Wert im aktuellen Zeitraum erfasst wurde.
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return bool true wenn Wert existiert, false wenn nicht
     */
    public function hasCurrentValue(KPI $kpi): bool
    {
        $currentPeriod = $kpi->getCurrentPeriod();
        $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $currentPeriod);

        return null !== $existingValue;
    }

    /**
     * Berechnet umfassende Statistiken für eine KPI über den Statistics Domain Service.
     *
     * @param KPI $kpi Die KPI für die Statistik-Berechnung
     *
     * @return array Statistiken als Array (Legacy-Format für Backward Compatibility)
     */
    public function getKpiStatistics(KPI $kpi): array
    {
        $statisticsVO = $this->statisticsService->calculateStatistics($kpi);
        
        return $statisticsVO->toArray();
    }

    /**
     * Berechnet Statistiken als Value Object für erweiterte Funktionen.
     *
     * @param KPI $kpi Die KPI für die Statistik-Berechnung
     *
     * @return KPIStatistics Statistiken als Value Object
     */
    public function getKpiStatisticsValueObject(KPI $kpi): KPIStatistics
    {
        return $this->statisticsService->calculateStatistics($kpi);
    }

    /**
     * Validiert eine KPI vor dem Speichern über den Validation Domain Service.
     *
     * @param KPI $kpi Die zu validierende KPI
     *
     * @return array Liste der Validierungsfehler (leer = valide)
     */
    public function validateKpi(KPI $kpi): array
    {
        return $this->validationService->validateKpi($kpi);
    }

    /**
     * Erweiterte KPI-Validierung mit Kontext.
     *
     * @param KPI   $kpi     Die zu validierende KPI
     * @param array $context Zusätzlicher Kontext für Validierung
     *
     * @return array Liste der Validierungsfehler (leer = valide)
     */
    public function validateKpiWithContext(KPI $kpi, array $context = []): array
    {
        return $this->validationService->validateWithContext($kpi, $context);
    }

    /**
     * Speichert einen neuen KPI-Wert mit umfassender Validierung und Event-Publishing.
     *
     * Arbeitsablauf:
     * 1. Duplikat-Prüfung über DuplicateDetection Domain Service
     * 2. Validierung des KPI-Wertes
     * 3. Persistierung mit Transaktions-Management
     * 4. Datei-Upload-Verarbeitung (optional)
     * 5. Domain Event Publishing
     * 6. Status-Update-Prüfung
     *
     * @param KPIValue   $kpiValue      Der zu speichernde KPI-Wert
     * @param array|null $uploadedFiles Optional hochgeladene Dateien
     *
     * @return array Ergebnis mit Status und Details
     */
    public function addValue(KPIValue $kpiValue, ?array $uploadedFiles = null): array
    {
        // 1. Duplikat-Prüfung über Domain Service
        $duplicateResult = $this->validationService->checkForDuplicates(
            $kpiValue->getKpi(),
            $kpiValue->getPeriod(),
            $kpiValue->getValue()
        );

        if ($duplicateResult) {
            return [
                'status' => 'duplicate',
                'message' => 'Duplicate value detected'
            ];
        }

        // 2. KPI-Wert-Validierung
        $validationErrors = $this->validationService->validateKpiValue($kpiValue);
        if (!empty($validationErrors)) {
            return [
                'status' => 'validation_error',
                'errors' => $validationErrors
            ];
        }

        // 3. Vorherigen Status für Event-Tracking merken
        $previousStatus = $this->getKpiStatus($kpiValue->getKpi());

        // 4. Transaktionale Persistierung
        $this->entityManager->beginTransaction();
        try {
            // KPI-Wert speichern
            $this->entityManager->persist($kpiValue);
            $this->entityManager->flush();

            // Optional: Datei-Uploads verarbeiten
            $uploadStats = [];
            if ($uploadedFiles) {
                $uploadStats = $this->fileUploadService->handleFileUploads($uploadedFiles, $kpiValue);
                $this->entityManager->flush();
            }

            // 5. Domain Events von der KPI-Entity abrufen und dispatchen
            $kpi = $kpiValue->getKpi();
            $events = $kpi->getRecordedEvents();
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            // 6. Status-Änderungs-Prüfung (für KPIBecameOverdue Event)
            $currentStatus = $this->getKpiStatus($kpi);
            if ($previousStatus !== $currentStatus) {
                $kpi->checkAndRecordStatusChange($previousStatus);
                
                // Neue Events dispatchen
                $statusChangeEvents = $kpi->getRecordedEvents();
                foreach ($statusChangeEvents as $event) {
                    $this->eventDispatcher->dispatch($event);
                }
            }

            $this->entityManager->commit();

            return [
                'status' => 'success',
                'kpi_value' => $kpiValue,
                'upload' => $uploadStats,
                'events_dispatched' => count($events),
                'status_changed' => $previousStatus !== $currentStatus
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Prüft, ob eine KPI bald fällig ist (Status = gelb).
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return bool true wenn KPI in den nächsten Tagen fällig wird
     */
    public function isDueSoon(KPI $kpi): bool
    {
        return $this->statusService->calculateStatus($kpi)->isYellow();
    }

    /**
     * Prüft, ob eine KPI überfällig ist (Status = rot).
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return bool true wenn KPI bereits überfällig ist
     */
    public function isOverdue(KPI $kpi): bool
    {
        return $this->statusService->calculateStatus($kpi)->isRed();
    }

    /**
     * Berechnet das nächste Fälligkeitsdatum einer KPI über den Status Domain Service.
     *
     * @param KPI $kpi Die KPI für die Fälligkeits-Berechnung
     *
     * @return \DateTimeImmutable Das berechnete Fälligkeitsdatum
     */
    public function calculateDueDate(KPI $kpi): \DateTimeImmutable
    {
        return $this->statusService->calculateDueDate($kpi);
    }

    /**
     * Berechnet die Anzahl Tage seit/bis zur Fälligkeit über den Status Domain Service.
     *
     * @param KPI $kpi Die zu prüfende KPI
     *
     * @return int Positive Zahl = Tage überfällig, negative Zahl = Tage bis Fälligkeit
     */
    public function getDaysOverdue(KPI $kpi): int
    {
        return $this->statusService->getDaysOverdue($kpi);
    }

    /**
     * Ermittelt alle KPIs die eine Erinnerung benötigen über den Reminder Domain Service.
     *
     * @param array $kpis       Liste der zu prüfenden KPIs
     * @param int   $daysBefore Tage vor Fälligkeit für Vorab-Erinnerung
     * @param int   $daysAfter  Tage nach Fälligkeit für Nachhak-Erinnerung
     *
     * @return array Liste von Erinnerungs-Objekten
     */
    public function getKpisForReminder(array $kpis, int $daysBefore = 3, int $daysAfter = 0): array
    {
        return $this->reminderService->getKpisForReminder($kpis, $daysBefore, $daysAfter);
    }

    /**
     * Erstellt personalisierten Erinnerungstext über den Reminder Domain Service.
     *
     * @param KPI    $kpi  Die KPI für die Erinnerung
     * @param string $type Erinnerungs-Typ (upcoming, due_today, overdue)
     * @param int    $days Anzahl Tage bis/seit Fälligkeit
     *
     * @return string Personalisierte Erinnerungsnachricht
     */
    public function createReminderMessage(KPI $kpi, string $type, int $days): string
    {
        return $this->reminderService->createPersonalizedMessage($kpi, $type, $days);
    }

    /**
     * Führt eine Bulk-Operation für mehrere KPIs durch.
     *
     * @param array  $kpis      Liste der KPIs
     * @param string $operation Operation: 'validate', 'calculate_status', 'get_statistics'
     * @param array  $context   Zusätzlicher Kontext
     *
     * @return array Ergebnisse der Bulk-Operation
     */
    public function performBulkOperation(array $kpis, string $operation, array $context = []): array
    {
        return match ($operation) {
            'validate' => $this->validationService->validateMultipleKpis($kpis, $context),
            'calculate_status' => $this->statusService->calculateStatusForMultiple($kpis),
            'get_statistics' => $this->statisticsService->calculateBulkStatistics($kpis),
            'check_reminders' => $this->reminderService->getKpisForReminder($kpis),
            default => throw new \InvalidArgumentException("Unknown bulk operation: {$operation}")
        };
    }

    /**
     * Erstellt eine neue KPI mit vollständiger Domain Event-Unterstützung.
     *
     * @param array $kpiData KPI-Daten (name, interval, user, etc.)
     * @param array $context Zusätzlicher Kontext für Creation Event
     *
     * @return array Ergebnis der KPI-Erstellung
     */
    public function createKpi(array $kpiData, array $context = []): array
    {
        // Validierung der Input-Daten
        $validationErrors = $this->validationService->validateKpiData($kpiData);
        if (!empty($validationErrors)) {
            return [
                'status' => 'validation_error',
                'errors' => $validationErrors
            ];
        }

        $this->entityManager->beginTransaction();
        try {
            // KPI über Factory-Methode erstellen (triggert KPICreated Event)
            $kpi = KPI::create(
                name: $kpiData['name'],
                interval: $kpiData['interval'],
                user: $kpiData['user'],
                description: $kpiData['description'] ?? null,
                unit: $kpiData['unit'] ?? null,
                target: $kpiData['target'] ?? null,
                context: $context
            );

            $this->entityManager->persist($kpi);
            $this->entityManager->flush();

            // Domain Events dispatchen
            $events = $kpi->getRecordedEvents();
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            $this->entityManager->commit();

            return [
                'status' => 'success',
                'kpi' => $kpi,
                'events_dispatched' => count($events)
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Führt eine umfassende KPI-Analyse durch.
     *
     * @param KPI $kpi Die zu analysierende KPI
     *
     * @return array Vollständige Analyse-Ergebnisse
     */
    public function performKpiAnalysis(KPI $kpi): array
    {
        $values = $this->kpiValueRepository->findByKPI($kpi);
        
        return [
            'kpi' => $kpi,
            'status' => $this->statusService->calculateStatus($kpi),
            'statistics' => $this->statisticsService->calculateStatistics($kpi),
            'trend' => $this->trendService->calculateTrend($kpi),
            'validation' => $this->validationService->validateKpi($kpi),
            'reminder_info' => $this->reminderService->shouldReceiveReminder($kpi),
            'duplicate_risks' => $this->duplicateDetectionService->identifyPatterns($kpi),
            'analysis_timestamp' => new \DateTimeImmutable()
        ];
    }

    /**
     * Prüft auf Duplikate bei KPI-Werten.
     */
    public function checkForDuplicates(KPI $kpi, Period $period, DecimalValue $value): bool
    {
        return $this->validationService->checkForDuplicates($kpi, $period, $value);
    }

    /**
     * Validiert mehrere KPIs in einem Batch.
     */
    public function validateMultipleKpis(array $kpis, array $options = []): array
    {
        return $this->validationService->validateMultipleKpis($kpis, $options);
    }

    /**
     * Berechnet den Trend für eine KPI.
     */
    public function calculateTrend(KPI $kpi, array $options = []): KPITrend
    {
        return $this->trendService->calculateTrend($kpi, $options);
    }
}
