<?php

namespace App\Tests\Domain\Event;

use App\Domain\Event\KPICreated;
use App\Entity\KPI;
use App\Entity\User;
use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\EmailAddress;
use App\Domain\ValueObject\KpiInterval;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für KPICreated Event.
 */
class KPICreatedTest extends TestCase
{
    private KPI $kpi;
    private User $user;

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
    }

    public function testConstructorSetsAllProperties(): void
    {
        // Arrange
        $context = ['source' => 'manual'];
        $occurredOn = new \DateTimeImmutable();

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            $occurredOn,
            $context
        );

        // Assert
        $this->assertSame($this->kpi, $event->kpi);
        $this->assertSame($this->user, $event->user);
        $this->assertSame($occurredOn, $event->occurredOn);
        $this->assertEquals($context, $event->context);
    }

    public function testCreateFactoryMethod(): void
    {
        // Arrange
        $context = ['source' => 'template', 'template_id' => 'abc123'];

        // Act
        $event = KPICreated::create($this->kpi, $context);

        // Assert
        $this->assertSame($this->kpi, $event->kpi);
        $this->assertSame($this->user, $event->user);
        $this->assertEquals($context, $event->context);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    public function testGetEventIdReturnsUniqueId(): void
    {
        // Arrange - Mock KPI with ID
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(456);
        $kpiMock->method('getUser')->willReturn($this->user);

        $occurredOn = new \DateTimeImmutable('2025-09-04 10:15:30');

        // Act
        $event = new KPICreated(
            $kpiMock,
            $this->user,
            $occurredOn
        );

        // Assert
        $expectedId = 'kpi_created_456_20250904101530';
        $this->assertEquals($expectedId, $event->getEventId());
    }

    public function testGetEventTypeReturnsCorrectType(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertEquals('kpi.created', $event->getEventType());
    }

    public function testIsFirstKpiForUserReturnsTrueWhenFlagSet(): void
    {
        // Arrange
        $context = ['is_first_kpi' => true];

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->isFirstKpiForUser());
    }

    public function testIsFirstKpiForUserReturnsFalseByDefault(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertFalse($event->isFirstKpiForUser());
    }

    public function testIsCreatedFromTemplateReturnsTrueWhenTemplateIdPresent(): void
    {
        // Arrange
        $context = ['template_id' => 'template_123'];

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->isCreatedFromTemplate());
    }

    public function testIsCreatedFromTemplateReturnsFalseWhenNoTemplateId(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertFalse($event->isCreatedFromTemplate());
    }

    public function testGetTemplateIdReturnsIdWhenPresent(): void
    {
        // Arrange
        $templateId = 'template_456';
        $context = ['template_id' => $templateId];

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertEquals($templateId, $event->getTemplateId());
    }

    public function testGetTemplateIdReturnsNullWhenNotPresent(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertNull($event->getTemplateId());
    }

    public function testIsImportedKpiReturnsTrueWhenFlagSet(): void
    {
        // Arrange
        $context = ['imported' => true];

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->isImportedKpi());
    }

    public function testIsImportedKpiReturnsFalseByDefault(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertFalse($event->isImportedKpi());
    }

    /**
     * @dataProvider creationSourceProvider
     */
    public function testGetCreationSourceReturnsCorrectSource(array $context, string $expectedSource): void
    {
        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertEquals($expectedSource, $event->getCreationSource());
    }

    public static function creationSourceProvider(): array
    {
        return [
            'imported' => [['imported' => true], 'import'],
            'from template' => [['template_id' => 'abc123'], 'template'],
            'custom source' => [['source' => 'api'], 'api'],
            'manual default' => [[], 'manual'],
        ];
    }

    /**
     * @dataProvider complexityLevelProvider
     */
    public function testGetComplexityLevelBasedOnKpiProperties(
        ?DecimalValue $target,
        ?string $description,
        ?string $unit,
        KpiInterval $interval,
        string $expectedComplexity
    ): void {
        // Arrange
        $this->kpi->setTarget($target)
                  ->setDescription($description)
                  ->setUnit($unit)
                  ->setInterval($interval);

        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertEquals($expectedComplexity, $event->getComplexityLevel());
    }

    public static function complexityLevelProvider(): array
    {
        return [
            'simple: basic kpi' => [null, null, null, KpiInterval::MONTHLY, 'simple'],
            'simple: with target' => [DecimalValue::fromString('100.00'), null, null, KpiInterval::MONTHLY, 'simple'],
            'moderate: with target and description' => [DecimalValue::fromString('100.00'), 'Test description', null, KpiInterval::MONTHLY, 'moderate'],
            'moderate: with all except quarterly' => [DecimalValue::fromString('100.00'), 'Test description', 'Euro', KpiInterval::MONTHLY, 'moderate'],
            'complex: all fields including quarterly' => [DecimalValue::fromString('100.00'), 'Test description', 'Euro', KpiInterval::QUARTERLY, 'complex'],
        ];
    }

    public function testGetRecommendedNextStepsIncludesAddFirstValue(): void
    {
        // Act
        $event = $this->createEvent();
        $steps = $event->getRecommendedNextSteps();

        // Assert
        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
        
        $addValueStep = array_filter($steps, fn($step) => $step['type'] === 'add_first_value');
        $this->assertNotEmpty($addValueStep);
        
        $step = array_values($addValueStep)[0];
        $this->assertEquals('Ersten KPI-Wert erfassen', $step['label']);
        $this->assertEquals('high', $step['priority']);
    }

    public function testGetRecommendedNextStepsIncludesSetTargetWhenMissing(): void
    {
        // Arrange - KPI without target
        $this->kpi->setTarget(null);

        // Act
        $event = $this->createEvent();
        $steps = $event->getRecommendedNextSteps();

        // Assert
        $setTargetSteps = array_filter($steps, fn($step) => $step['type'] === 'set_target');
        $this->assertNotEmpty($setTargetSteps);
        
        $step = array_values($setTargetSteps)[0];
        $this->assertEquals('Zielwert definieren', $step['label']);
        $this->assertEquals('medium', $step['priority']);
    }

    public function testGetRecommendedNextStepsDoesNotIncludeSetTargetWhenPresent(): void
    {
        // Arrange - KPI with target
        $this->kpi->setTarget(DecimalValue::fromString('100.00'));

        // Act
        $event = $this->createEvent();
        $steps = $event->getRecommendedNextSteps();

        // Assert
        $setTargetSteps = array_filter($steps, fn($step) => $step['type'] === 'set_target');
        $this->assertEmpty($setTargetSteps);
    }

    public function testGetRecommendedNextStepsIncludesAddDescriptionWhenMissing(): void
    {
        // Arrange - KPI without description
        $this->kpi->setDescription(null);

        // Act
        $event = $this->createEvent();
        $steps = $event->getRecommendedNextSteps();

        // Assert
        $addDescriptionSteps = array_filter($steps, fn($step) => $step['type'] === 'add_description');
        $this->assertNotEmpty($addDescriptionSteps);
        
        $step = array_values($addDescriptionSteps)[0];
        $this->assertEquals('Beschreibung hinzufügen', $step['label']);
        $this->assertEquals('low', $step['priority']);
    }

    public function testGetRecommendedNextStepsIncludesTutorialForFirstKpi(): void
    {
        // Arrange
        $context = ['is_first_kpi' => true];

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );
        
        $steps = $event->getRecommendedNextSteps();

        // Assert
        $tutorialSteps = array_filter($steps, fn($step) => $step['type'] === 'tutorial');
        $this->assertNotEmpty($tutorialSteps);
        
        $step = array_values($tutorialSteps)[0];
        $this->assertEquals('KPI-Tutorial starten', $step['label']);
        $this->assertEquals('high', $step['priority']);
        
        // Tutorial should be first step
        $this->assertEquals('tutorial', $steps[0]['type']);
    }

    /**
     * @dataProvider autoTagsProvider
     */
    public function testGenerateAutoTagsBasedOnKpiProperties(
        string $kpiName,
        KpiInterval $interval,
        array $expectedTags
    ): void {
        // Arrange
        $this->kpi->setName($kpiName)->setInterval($interval);

        // Act
        $event = $this->createEvent();
        $tags = $event->generateAutoTags();

        // Assert
        foreach ($expectedTags as $expectedTag) {
            $this->assertContains($expectedTag, $tags);
        }
    }

    public static function autoTagsProvider(): array
    {
        return [
            'financial umsatz' => [
                'Monatlicher Umsatz', 
                KpiInterval::MONTHLY, 
                ['monthly', 'financial', 'complexity-simple', 'source-manual']
            ],
            'customer related' => [
                'Anzahl Kunden', 
                KpiInterval::WEEKLY, 
                ['weekly', 'customer', 'complexity-simple', 'source-manual']
            ],
            'cost tracking' => [
                'Betriebskosten', 
                KpiInterval::QUARTERLY, 
                ['quarterly', 'cost', 'complexity-simple', 'source-manual']
            ],
            'percentage kpi' => [
                'Conversion Rate %', 
                KpiInterval::MONTHLY, 
                ['monthly', 'percentage', 'complexity-simple', 'source-manual']
            ],
        ];
    }

    public function testGenerateAutoTagsIncludesComplexityAndSource(): void
    {
        // Arrange
        $this->kpi->setTarget(DecimalValue::fromString('100.00'))
                  ->setDescription('Complex KPI')
                  ->setUnit('Euro')
                  ->setInterval(KpiInterval::QUARTERLY);
        
        $context = ['source' => 'import'];

        // Act
        $event = new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );
        
        $tags = $event->generateAutoTags();

        // Assert
        $this->assertContains('complexity-complex', $tags);
        $this->assertContains('source-import', $tags);
        $this->assertContains('quarterly', $tags);
    }

    public function testToArrayExportsAllRelevantData(): void
    {
        // Arrange
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(789);
        $kpiMock->method('getName')->willReturn('Revenue KPI');
        $kpiMock->method('getInterval')->willReturn(KpiInterval::QUARTERLY);
        $kpiMock->method('getUnit')->willReturn('Euro');
        $kpiMock->method('getTarget')->willReturn(DecimalValue::fromString('50000.00'));
        $kpiMock->method('getDescription')->willReturn('Quarterly revenue tracking');
        $kpiMock->method('getUser')->willReturn($this->user);
        
        $userMock = $this->createMock(User::class);
        $userMock->method('getId')->willReturn(101);
        $userMock->method('getEmail')->willReturn(EmailAddress::fromString('user@example.com'));

        $context = ['is_first_kpi' => true, 'source' => 'manual'];
        $occurredOn = new \DateTimeImmutable('2025-09-04 16:45:00');

        // Act
        $event = new KPICreated(
            $kpiMock,
            $userMock,
            $occurredOn,
            $context
        );

        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertEquals('kpi_created_789_20250904164500', $array['event_id']);
        $this->assertEquals('kpi.created', $array['event_type']);
        $this->assertEquals('2025-09-04T16:45:00+00:00', $array['occurred_on']);
        $this->assertEquals(789, $array['kpi_id']);
        $this->assertEquals('Revenue KPI', $array['kpi_name']);
        $this->assertEquals('quarterly', $array['kpi_interval']);
        $this->assertEquals('Euro', $array['kpi_unit']);
        $this->assertTrue($array['has_target']);
        $this->assertTrue($array['has_description']);
        $this->assertEquals(101, $array['user_id']);
        $this->assertEquals('user@example.com', $array['user_email']);
        $this->assertEquals('manual', $array['creation_source']);
        $this->assertEquals('complex', $array['complexity_level']);
        $this->assertTrue($array['is_first_kpi']);
        $this->assertNull($array['template_id']);
        $this->assertArrayHasKey('auto_tags', $array);
        $this->assertArrayHasKey('recommended_next_steps', $array);
        $this->assertEquals($context, $array['context']);
    }

    public function testToArrayHandlesNullValues(): void
    {
        // Arrange - KPI with minimal data
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(999);
        $kpiMock->method('getName')->willReturn('Basic KPI');
        $kpiMock->method('getInterval')->willReturn(null);
        $kpiMock->method('getUnit')->willReturn(null);
        $kpiMock->method('getTarget')->willReturn(null);
        $kpiMock->method('getDescription')->willReturn('');
        $kpiMock->method('getUser')->willReturn($this->user);

        // Act
        $event = new KPICreated(
            $kpiMock,
            $this->user,
            new \DateTimeImmutable()
        );

        $array = $event->toArray();

        // Assert
        $this->assertNull($array['kpi_interval']);
        $this->assertNull($array['kpi_unit']);
        $this->assertFalse($array['has_target']);
        $this->assertFalse($array['has_description']);
        $this->assertEquals('manual', $array['creation_source']);
        $this->assertEquals('simple', $array['complexity_level']);
        $this->assertFalse($array['is_first_kpi']);
    }

    private function createEvent(array $context = []): KPICreated
    {
        return new KPICreated(
            $this->kpi,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );
    }
}
