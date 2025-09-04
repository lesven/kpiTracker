<?php

namespace App\Tests\Domain\Service;

use App\Domain\Service\KPIReminderDomainService;
use App\Domain\Service\KPIStatusDomainService;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Repository\KPIValueRepository;
use PHPUnit\Framework\TestCase;

/**
 * Test für den KPIReminderDomainService.
 *
 * Testet alle Erinnerungs-Logik, Anti-Spam-Mechanismen und
 * personalisierten Nachrichten-Generierung.
 */
class KPIReminderDomainServiceTest extends TestCase
{
    private KPIReminderDomainService $service;
    private KPIStatusDomainService $statusService;
    private KPIValueRepository $kpiValueRepository;

    protected function setUp(): void
    {
        $this->statusService = $this->createMock(KPIStatusDomainService::class);
        $this->kpiValueRepository = $this->createMock(KPIValueRepository::class);
        $this->service = new KPIReminderDomainService($this->statusService, $this->kpiValueRepository);
    }

    /**
     * @test
     */
    public function findet_kpis_die_vorab_erinnerung_benoetigen(): void
    {
        $kpi = $this->createMockKPI('Test KPI', KpiInterval::WEEKLY);
        
        // KPI ist in 3 Tagen fällig (Vorab-Erinnerung)
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('+3 days'));
            
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturn(null); // Kein Wert vorhanden

        $reminders = $this->service->getKpisForReminder([$kpi]);

        $this->assertCount(1, $reminders);
        $this->assertEquals('upcoming', $reminders[0]['type']);
        $this->assertEquals($kpi, $reminders[0]['kpi']);
    }

    /**
     * @test
     */
    public function findet_kpis_die_heute_faellig_sind(): void
    {
        $kpi = $this->createMockKPI('Today Due KPI', KpiInterval::MONTHLY);
        
        // Override the default mock and set explicit due date
        $today = new \DateTimeImmutable('today midnight');
        $kpiOverride = $this->createMock(KPI::class);
        $kpiOverride->method('getName')->willReturn('Today Due KPI');
        $kpiOverride->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $kpiOverride->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $kpiOverride->method('getId')->willReturn(1);
        $kpiOverride->method('getUser')->willReturn($this->createMock(\App\Entity\User::class));
        $kpiOverride->method('getNextDueDate')->willReturn($today);
            
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturn(null);

        $reminders = $this->service->getKpisForReminder([$kpiOverride]);

        $this->assertCount(1, $reminders);
        $this->assertEquals('due_today', $reminders[0]['type']);
    }

    /**
     * @test
     */
    public function findet_ueberfaellige_kpis(): void
    {
        $kpi = $this->createMockKPI('Overdue KPI', KpiInterval::QUARTERLY);
        
        // Override the default mock for overdue
        $overdue = new \DateTimeImmutable('-5 days midnight');
        $kpiOverdue = $this->createMock(KPI::class);
        $kpiOverdue->method('getName')->willReturn('Overdue KPI');
        $kpiOverdue->method('getInterval')->willReturn(KpiInterval::QUARTERLY);
        $kpiOverdue->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $kpiOverdue->method('getId')->willReturn(1);
        $kpiOverdue->method('getUser')->willReturn($this->createMock(\App\Entity\User::class));
        $kpiOverdue->method('getNextDueDate')->willReturn($overdue);
            
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturn(null);

        $reminders = $this->service->getKpisForReminder([$kpiOverdue]);

        $this->assertCount(1, $reminders);
        $this->assertEquals('overdue', $reminders[0]['type']);
    }

    /**
     * @test
     */
    public function ignoriert_kpis_mit_aktuellen_werten(): void
    {
        $kpi = $this->createMockKPI('Current KPI', KpiInterval::WEEKLY);
        
        // KPI ist überfällig, hat aber bereits einen Wert
        $kpi->method('getNextDueDate')
            ->willReturn(new \DateTimeImmutable('-2 days'));
            
        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturn($this->createMock(KPIValue::class)); // Wert vorhanden

        $reminders = $this->service->getKpisForReminder([$kpi]);

        $this->assertEmpty($reminders);
    }

    /**
     * @test
     */
    public function erstellt_personalisierte_nachrichten(): void
    {
        $user = $this->createMockUser('Max Mustermann');
        $kpi = $this->getMockBuilder(KPI::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getUser', 'getInterval', 'getCurrentPeriod', 'getId'])
            ->getMock();
        
        $kpi->method('getName')->willReturn('Umsatz Q1');
        $kpi->method('getUser')->willReturn($user);
        $kpi->method('getInterval')->willReturn(KpiInterval::QUARTERLY);
        $kpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-Q1'));
        $kpi->method('getId')->willReturn(1);

        // Vorab-Erinnerung
        $message = $this->service->createPersonalizedMessage($kpi, 'upcoming', 3);
        $this->assertStringContainsString('Max', $message);
        $this->assertStringContainsString('Umsatz Q1', $message);
        $this->assertStringContainsString('3 Tagen', $message);

        // Heute fällig
        $message = $this->service->createPersonalizedMessage($kpi, 'due_today', 0);
        $this->assertStringContainsString('heute', $message);

        // Überfällig
        $message = $this->service->createPersonalizedMessage($kpi, 'overdue', 5);
        $this->assertStringContainsString('überfällig', $message);
        $this->assertStringContainsString('5 Tagen', $message);
    }

    /**
     * @test
     */
    public function beruecksichtigt_benutzer_praeferenzen(): void
    {
        $user = $this->createMockUser('Anna Schmidt');
        $kpi = $this->getMockBuilder(KPI::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getUser', 'getInterval', 'getCurrentPeriod', 'getId'])
            ->getMock();
            
        $kpi->method('getName')->willReturn('Verkaufszahlen');
        $kpi->method('getUser')->willReturn($user);
        $kpi->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $kpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $kpi->method('getId')->willReturn(1);

        $preferences = [
            'reminder_style' => 'formal',
            'include_statistics' => true,
            'language' => 'de'
        ];

        $message = $this->service->createPersonalizedMessage($kpi, 'upcoming', 2, $preferences);

        $this->assertStringContainsString('Frau Schmidt', $message); // Formal
        $this->assertStringContainsString('Verkaufszahlen', $message);
    }

    /**
     * @test
     */
    public function kann_erinnerungsfrequenz_pruefen(): void
    {
        $kpi = $this->createMockKPI('Test KPI', KpiInterval::WEEKLY);
        
        // KPI sollte Erinnerung erhalten
        $this->assertTrue($this->service->shouldReceiveReminder($kpi));
        
        // Nach kürzlicher Erinnerung (Anti-Spam)
        $recentReminder = new \DateTimeImmutable('-2 hours');
        $this->assertFalse($this->service->shouldReceiveReminder($kpi, $recentReminder));
        
        // Nach längerer Zeit wieder erlaubt
        $oldReminder = new \DateTimeImmutable('-25 hours');
        $this->assertTrue($this->service->shouldReceiveReminder($kpi, $oldReminder));
    }

    /**
     * @test
     */
    public function kann_eskalations_workflow_handhaben(): void
    {
        $kpi = $this->createMockKPI('Critical KPI', KpiInterval::WEEKLY);
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('-10 days'));
        
        $escalation = $this->service->calculateEscalationLevel($kpi, 10); // 10 Tage überfällig
        
        $this->assertEquals(4, $escalation['level']); // Höchste Eskalation bei 10 Tagen
        $this->assertEquals('critical', $escalation['urgency']);
        $this->assertContains('Management', $escalation['notify_roles']);
    }

    /**
     * @test
     */
    public function kann_batch_erinnerungen_verarbeiten(): void
    {
        $kpi1 = $this->createMockKPI('KPI 1', KpiInterval::WEEKLY);
        $kpi2 = $this->createMockKPI('KPI 2', KpiInterval::MONTHLY);
        $kpi3 = $this->createMockKPI('KPI 3', KpiInterval::QUARTERLY);

        $this->statusService
            ->method('getDaysOverdue')
            ->willReturnOnConsecutiveCalls(-3, 0, 5); // Vorab, heute, überfällig

        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturn(null); // Alle ohne Werte

        $batchResult = $this->service->processBatchReminders([$kpi1, $kpi2, $kpi3]);

        $this->assertEquals(3, $batchResult['total_processed']);
        $this->assertEquals(3, $batchResult['reminders_created']);
        $this->assertCount(3, $batchResult['reminders']);
    }

    /**
     * @test
     */
    public function kann_erinnerungs_templates_verwenden(): void
    {
        $kpi = $this->createMockKPI('Sales KPI', KpiInterval::MONTHLY);
        
        $template = $this->service->getMessageTemplate('upcoming', 'sales');
        $this->assertStringContainsString('{kpi_name}', $template);
        $this->assertStringContainsString('{days}', $template);
        
        $customizedMessage = $this->service->applyTemplate($template, [
            'kpi_name' => $kpi->getName(),
            'days' => 3,
            'user_name' => 'Test User'
        ]);
        
        $this->assertStringContainsString('Sales KPI', $customizedMessage);
        $this->assertStringNotContainsString('{kpi_name}', $customizedMessage);
    }

    /**
     * @test
     */
    public function kann_intelligente_zeitplanung_durchfuehren(): void
    {
        $user = $this->createMockUser('Test User');
        $kpi = $this->createMockKPI('Morning KPI', KpiInterval::WEEKLY);
        $kpi->method('getUser')->willReturn($user);

        // Benutzer-Präferenzen für Erinnerungszeit
        $preferences = [
            'preferred_reminder_time' => '09:00',
            'timezone' => 'Europe/Berlin',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
        ];

        $scheduledTime = $this->service->calculateOptimalReminderTime($kpi, $preferences);
        
        $this->assertEquals('09:00', $scheduledTime->format('H:i'));
        $this->assertContains(strtolower($scheduledTime->format('l')), $preferences['working_days']);
    }

    /**
     * @test
     */
    public function kann_mehrsprachige_nachrichten_erstellen(): void
    {
        $kpi = $this->createMockKPI('Test KPI', KpiInterval::WEEKLY);
        
        $germanMessage = $this->service->createLocalizedMessage($kpi, 'upcoming', 3, 'de');
        $this->assertStringContainsString('Tagen', $germanMessage);
        
        $englishMessage = $this->service->createLocalizedMessage($kpi, 'upcoming', 3, 'en');
        $this->assertStringContainsString('days', $englishMessage);
    }

    /**
     * @test
     */
    public function kann_erinnerungs_statistiken_generieren(): void
    {
        $kpis = [
            $this->createMockKPI('KPI 1', KpiInterval::WEEKLY),
            $this->createMockKPI('KPI 2', KpiInterval::MONTHLY),
            $this->createMockKPI('KPI 3', KpiInterval::QUARTERLY)
        ];

        $this->statusService
            ->method('getDaysOverdue')
            ->willReturnOnConsecutiveCalls(-2, 0, 3);

        $this->kpiValueRepository
            ->method('findByKpiAndPeriod')
            ->willReturn(null);

        $stats = $this->service->generateReminderStatistics($kpis);

        $this->assertEquals(3, $stats['total_kpis']);
        $this->assertEquals(3, $stats['needs_reminder']);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('by_urgency', $stats);
    }

    /**
     * Erstellt einen Mock KPI für Tests.
     */
    private function createMockKPI(string $name, KpiInterval $interval): KPI
    {
        $kpi = $this->getMockBuilder(KPI::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getInterval', 'getCurrentPeriod', 'getId', 'getUser', 'getNextDueDate'])
            ->getMock();
            
        $kpi->method('getName')->willReturn($name);
        $kpi->method('getInterval')->willReturn($interval);
        $kpi->method('getCurrentPeriod')->willReturn(Period::fromString('2024-01'));
        $kpi->method('getId')->willReturn(1);
        $kpi->method('getUser')->willReturn($this->createMock(\App\Entity\User::class));
        $kpi->method('getNextDueDate')->willReturn(new \DateTimeImmutable('+3 days'));
        
        return $kpi;
    }

    /**
     * Erstellt einen Mock User für Tests.
     */
    private function createMockUser(string $name): User
    {
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFullName', 'getFirstName'])
            ->getMock();
            
        $user->method('getFullName')->willReturn($name);
        $user->method('getFirstName')->willReturn(explode(' ', $name)[0]);
        
        return $user;
    }
}