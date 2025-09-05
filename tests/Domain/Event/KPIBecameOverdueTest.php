<?php

namespace App\Tests\Domain\Event;

use App\Domain\Event\KPIBecameOverdue;
use App\Domain\ValueObject\KPIStatus;
use App\Entity\KPI;
use App\Entity\User;
use App\Domain\ValueObject\EmailAddress;
use App\Domain\ValueObject\KpiInterval;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für KPIBecameOverdue Event.
 */
class KPIBecameOverdueTest extends TestCase
{
    private KPI $kpi;
    private User $user;
    private KPIStatus $previousStatus;
    private KPIStatus $currentStatus;
    private \DateTimeImmutable $dueDate;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setEmail(EmailAddress::fromString('test@example.com'))
                  ->setFirstName('Test')
                  ->setLastName('User');

        $this->kpi = new KPI();
        $this->kpi->setName('Test KPI')
                  ->setInterval(KpiInterval::MONTHLY)
                  ->setUser($this->user);

        $this->previousStatus = KPIStatus::yellow();
        $this->currentStatus = KPIStatus::red();
        $this->dueDate = new \DateTimeImmutable('2025-08-31');
    }

    public function testConstructorSetsAllProperties(): void
    {
        // Arrange
        $context = ['test_key' => 'test_value'];
        $occurredOn = new \DateTimeImmutable();

        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            5,
            $this->dueDate,
            $occurredOn,
            $context
        );

        // Assert
        $this->assertSame($this->kpi, $event->kpi);
        $this->assertSame($this->user, $event->user);
        $this->assertSame($this->previousStatus, $event->previousStatus);
        $this->assertSame($this->currentStatus, $event->currentStatus);
        $this->assertEquals(5, $event->daysOverdue);
        $this->assertSame($this->dueDate, $event->dueDate);
        $this->assertSame($occurredOn, $event->occurredOn);
        $this->assertEquals($context, $event->context);
    }

    public function testCreateFactoryMethod(): void
    {
        // Arrange
        $context = ['source' => 'scheduler'];

        // Act
        $event = KPIBecameOverdue::create(
            $this->kpi,
            $this->previousStatus,
            $this->currentStatus,
            3,
            $this->dueDate,
            $context
        );

        // Assert
        $this->assertSame($this->kpi, $event->kpi);
        $this->assertSame($this->user, $event->user);
        $this->assertSame($this->previousStatus, $event->previousStatus);
        $this->assertSame($this->currentStatus, $event->currentStatus);
        $this->assertEquals(3, $event->daysOverdue);
        $this->assertSame($this->dueDate, $event->dueDate);
        $this->assertEquals($context, $event->context);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    public function testGetEventIdReturnsUniqueId(): void
    {
        // Arrange - Mock KPI with ID
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(123);
        $kpiMock->method('getUser')->willReturn($this->user);

        $occurredOn = new \DateTimeImmutable('2025-09-04 14:30:00');

        // Act
        $event = new KPIBecameOverdue(
            $kpiMock,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            5,
            $this->dueDate,
            $occurredOn
        );

        // Assert
        $expectedId = 'kpi_became_overdue_123_20250904143000';
        $this->assertEquals($expectedId, $event->getEventId());
    }

    public function testGetEventTypeReturnsCorrectType(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertEquals('kpi.became.overdue', $event->getEventType());
    }

    public function testIsFirstTimeOverdueReturnsTrueForYellowStatus(): void
    {
        // Arrange
        $yellowStatus = KPIStatus::yellow();
        
        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $yellowStatus,
            $this->currentStatus,
            1,
            $this->dueDate,
            new \DateTimeImmutable()
        );

        // Assert
        $this->assertTrue($event->isFirstTimeOverdue());
    }

    public function testIsFirstTimeOverdueReturnsTrueForGreenStatus(): void
    {
        // Arrange
        $greenStatus = KPIStatus::green();
        
        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $greenStatus,
            $this->currentStatus,
            1,
            $this->dueDate,
            new \DateTimeImmutable()
        );

        // Assert
        $this->assertTrue($event->isFirstTimeOverdue());
    }

    public function testIsFirstTimeOverdueReturnsFalseForRedStatus(): void
    {
        // Arrange
        $redStatus = KPIStatus::red();
        
        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $redStatus,
            $this->currentStatus,
            1,
            $this->dueDate,
            new \DateTimeImmutable()
        );

        // Assert
        $this->assertFalse($event->isFirstTimeOverdue());
    }

    /**
     * @dataProvider escalationLevelProvider
     */
    public function testGetEscalationLevelReturnsCorrectLevel(int $daysOverdue, int $expectedLevel): void
    {
        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            $daysOverdue,
            $this->dueDate,
            new \DateTimeImmutable()
        );

        // Assert
        $this->assertEquals($expectedLevel, $event->getEscalationLevel());
    }

    public static function escalationLevelProvider(): array
    {
        return [
            'level 1: 1 day overdue' => [1, 1],
            'level 2: 2 days overdue' => [2, 2],
            'level 2: 3 days overdue' => [3, 2],
            'level 3: 4 days overdue' => [4, 3],
            'level 3: 7 days overdue' => [7, 3],
            'level 4: 8 days overdue' => [8, 4],
            'level 4: 14 days overdue' => [14, 4],
            'level 5: 15 days overdue' => [15, 5],
            'level 5: 30 days overdue' => [30, 5],
        ];
    }

    /**
     * @dataProvider criticalKpiProvider
     */
    public function testIsCriticalKpiDetectsCriticalKeywords(string $kpiName, bool $expectedCritical): void
    {
        // Arrange
        $this->kpi->setName($kpiName);

        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertEquals($expectedCritical, $event->isCriticalKPI());
    }

    public static function criticalKpiProvider(): array
    {
        return [
            'umsatz keyword' => ['Monatlicher Umsatz', true],
            'gewinn keyword' => ['Gewinn pro Quartal', true],
            'revenue keyword' => ['Monthly Revenue', true],
            'critical keyword' => ['Critical System Uptime', true],
            'kritisch keyword' => ['Kritische Fehlerrate', true],
            'normal kpi' => ['Anzahl Kunden', false],
            'performance kpi' => ['Ladezeit Website', false],
        ];
    }

    public function testIsCriticalKpiUsesContextFlag(): void
    {
        // Arrange
        $context = ['is_critical' => true];

        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            5,
            $this->dueDate,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->isCriticalKPI());
    }

    /**
     * @dataProvider urgencyLevelProvider
     */
    public function testGetUrgencyLevelBasedOnEscalationAndCriticality(
        int $daysOverdue,
        bool $isCritical,
        string $expectedUrgency
    ): void {
        // Arrange
        if ($isCritical) {
            $this->kpi->setName('Kritischer Umsatz');
        }

        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            $daysOverdue,
            $this->dueDate,
            new \DateTimeImmutable()
        );

        // Assert
        $this->assertEquals($expectedUrgency, $event->getUrgencyLevel());
    }

    public static function urgencyLevelProvider(): array
    {
        return [
            'critical kpi always critical urgency' => [1, true, 'critical'],
            'normal kpi, 1 day overdue' => [1, false, 'medium'],
            'normal kpi, 2 days overdue' => [2, false, 'medium'],
            'normal kpi, 3 days overdue' => [3, false, 'medium'],
            'normal kpi, 7 days overdue' => [7, false, 'high'],
            'normal kpi, 15 days overdue' => [15, false, 'critical'],
        ];
    }

    /**
     * @dataProvider nextReminderTimeProvider
     */
    public function testGetNextReminderTimeBasedOnEscalation(int $daysOverdue, int $expectedHours): void
    {
        // Arrange
        $occurredOn = new \DateTimeImmutable('2025-09-04 12:00:00');

        // Act
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            $daysOverdue,
            $this->dueDate,
            $occurredOn
        );

        // Assert
        $expectedTime = $occurredOn->modify("+{$expectedHours} hours");
        $this->assertEquals($expectedTime, $event->getNextReminderTime());
    }

    public static function nextReminderTimeProvider(): array
    {
        return [
            '1 day overdue: 24h reminder' => [1, 24],
            '2 days overdue: 12h reminder' => [2, 12],
            '4 days overdue: 8h reminder' => [4, 8],
            '8 days overdue: 4h reminder' => [8, 4],
            '15 days overdue: 2h reminder' => [15, 2],
        ];
    }

    public function testGetRecommendedActionsIncludesBasicAddValue(): void
    {
        // Act
        $event = $this->createEvent();
        $actions = $event->getRecommendedActions();

        // Assert
        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
        
        // Should include add_value action
        $addValueAction = array_filter($actions, fn($action) => $action['type'] === 'add_value');
        $this->assertNotEmpty($addValueAction);
        
        $addValueAction = array_values($addValueAction)[0];
        $this->assertEquals('KPI-Wert erfassen', $addValueAction['label']);
        $this->assertEquals('high', $addValueAction['priority']);
    }

    public function testGetRecommendedActionsIncludesSupportForHighEscalation(): void
    {
        // Arrange - High escalation (level 3+)
        $event = new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            7, // Level 3 escalation
            $this->dueDate,
            new \DateTimeImmutable()
        );

        // Act
        $actions = $event->getRecommendedActions();

        // Assert
        $supportActions = array_filter($actions, fn($action) => $action['type'] === 'contact_support');
        $this->assertNotEmpty($supportActions);
    }

    public function testGetRecommendedActionsIncludesEscalationForCriticalKpi(): void
    {
        // Arrange - Critical KPI
        $this->kpi->setName('Kritischer Umsatz');

        // Act
        $event = $this->createEvent();
        $actions = $event->getRecommendedActions();

        // Assert
        $escalateActions = array_filter($actions, fn($action) => $action['type'] === 'escalate_immediately');
        $this->assertNotEmpty($escalateActions);
        
        $escalateAction = array_values($escalateActions)[0];
        $this->assertEquals('critical', $escalateAction['priority']);
    }

    public function testToArrayExportsAllRelevantData(): void
    {
        // Arrange
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(123);
        $kpiMock->method('getName')->willReturn('Test KPI');
        $kpiMock->method('getInterval')->willReturn(KPIInterval::MONTHLY);
        $kpiMock->method('getUser')->willReturn($this->user);
        
        $userMock = $this->createMock(User::class);
        $userMock->method('getId')->willReturn(456);
        $userMock->method('getEmail')->willReturn(EmailAddress::fromString('user@test.com'));

        $context = ['test_context' => 'value'];
        $occurredOn = new \DateTimeImmutable('2025-09-04 15:30:00');

        // Act
        $event = new KPIBecameOverdue(
            $kpiMock,
            $userMock,
            $this->previousStatus,
            $this->currentStatus,
            5,
            $this->dueDate,
            $occurredOn,
            $context
        );

        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertEquals('kpi_became_overdue_123_20250904153000', $array['event_id']);
        $this->assertEquals('kpi.became.overdue', $array['event_type']);
        $this->assertEquals('2025-09-04T15:30:00+00:00', $array['occurred_on']);
        $this->assertEquals(123, $array['kpi_id']);
        $this->assertEquals('Test KPI', $array['kpi_name']);
        $this->assertEquals('monthly', $array['kpi_interval']);
        $this->assertEquals(456, $array['user_id']);
        $this->assertEquals('user@test.com', $array['user_email']);
        $this->assertEquals('yellow', $array['previous_status']);
        $this->assertEquals('red', $array['current_status']);
        $this->assertEquals(5, $array['days_overdue']);
        $this->assertEquals('2025-08-31T00:00:00+00:00', $array['due_date']);
        $this->assertEquals(3, $array['escalation_level']); // 5 days = level 3
        $this->assertEquals('high', $array['urgency_level']);
        $this->assertTrue($array['is_first_time_overdue']);
        $this->assertFalse($array['is_critical_kpi']);
        $this->assertArrayHasKey('next_reminder_time', $array);
        $this->assertArrayHasKey('recommended_actions', $array);
        $this->assertEquals($context, $array['context']);
    }

    private function createEvent(int $daysOverdue = 5): KPIBecameOverdue
    {
        return new KPIBecameOverdue(
            $this->kpi,
            $this->user,
            $this->previousStatus,
            $this->currentStatus,
            $daysOverdue,
            $this->dueDate,
            new \DateTimeImmutable()
        );
    }
}
