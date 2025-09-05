<?php

namespace App\Tests\Domain\Event;

use App\Domain\Event\KPIValueAdded;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\EmailAddress;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für KPIValueAdded Event.
 */
class KPIValueAddedTest extends TestCase
{
    private KPI $kpi;
    private KPIValue $kpiValue;
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

        $this->kpiValue = new KPIValue();
        $this->kpiValue->setKpi($this->kpi)
                       ->setValue(DecimalValue::fromString('100.50'))
                       ->setPeriod(Period::fromString('2025-09'));
    }

    public function testConstructorSetsAllProperties(): void
    {
        // Arrange
        $context = ['uploaded_files_count' => 2];
        $occurredOn = new \DateTimeImmutable();

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            $occurredOn,
            $context
        );

        // Assert
        $this->assertSame($this->kpi, $event->kpi);
        $this->assertSame($this->kpiValue, $event->kpiValue);
        $this->assertSame($this->user, $event->user);
        $this->assertSame($occurredOn, $event->occurredOn);
        $this->assertEquals($context, $event->context);
    }

    public function testCreateFactoryMethod(): void
    {
        // Arrange
        $context = ['is_first_value' => true];

        // Act
        $event = KPIValueAdded::create($this->kpi, $this->kpiValue, $context);

        // Assert
        $this->assertSame($this->kpi, $event->kpi);
        $this->assertSame($this->kpiValue, $event->kpiValue);
        $this->assertSame($this->user, $event->user);
        $this->assertEquals($context, $event->context);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    public function testGetEventIdReturnsUniqueId(): void
    {
        // Arrange - Mock entities with IDs
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(123);
        $kpiMock->method('getUser')->willReturn($this->user);

        $kpiValueMock = $this->createMock(KPIValue::class);
        $kpiValueMock->method('getId')->willReturn(456);

        $occurredOn = new \DateTimeImmutable('2025-09-04 09:30:15');

        // Act
        $event = new KPIValueAdded(
            $kpiMock,
            $kpiValueMock,
            $this->user,
            $occurredOn
        );

        // Assert
        $expectedId = 'kpi_value_added_123_456_20250904093015';
        $this->assertEquals($expectedId, $event->getEventId());
    }

    public function testGetEventTypeReturnsCorrectType(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertEquals('kpi.value.added', $event->getEventType());
    }

    public function testIsFirstValueReturnsTrueWhenFlagSet(): void
    {
        // Arrange
        $context = ['is_first_value' => true];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->isFirstValue());
    }

    public function testIsFirstValueReturnsFalseByDefault(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertFalse($event->isFirstValue());
    }

    public function testWasOverriddenReturnsTrueWhenFlagSet(): void
    {
        // Arrange
        $context = ['was_override' => true];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->wasOverridden());
    }

    public function testWasOverriddenReturnsFalseByDefault(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertFalse($event->wasOverridden());
    }

    public function testGetOverriddenValueReturnsValueWhenPresent(): void
    {
        // Arrange
        $overriddenValue = new KPIValue();
        $overriddenValue->setValue(DecimalValue::fromString('75.25'));
        
        $context = ['overridden_value' => $overriddenValue];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertSame($overriddenValue, $event->getOverriddenValue());
    }

    public function testGetOverriddenValueReturnsNullWhenNotPresent(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertNull($event->getOverriddenValue());
    }

    public function testHasFileUploadsReturnsTrueWhenFilesUploaded(): void
    {
        // Arrange
        $context = ['uploaded_files_count' => 3];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertTrue($event->hasFileUploads());
    }

    public function testHasFileUploadsReturnsFalseWhenNoFiles(): void
    {
        // Arrange
        $context = ['uploaded_files_count' => 0];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        // Assert
        $this->assertFalse($event->hasFileUploads());
    }

    public function testHasFileUploadsReturnsFalseByDefault(): void
    {
        // Act
        $event = $this->createEvent();

        // Assert
        $this->assertFalse($event->hasFileUploads());
    }

    public function testToArrayExportsAllRelevantData(): void
    {
        // Arrange
        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(100);
        $kpiMock->method('getName')->willReturn('Revenue KPI');
        $kpiMock->method('getInterval')->willReturn(KpiInterval::MONTHLY);
        $kpiMock->method('getUser')->willReturn($this->user);

        $kpiValueMock = $this->createMock(KPIValue::class);
        $kpiValueMock->method('getId')->willReturn(200);
        $kpiValueMock->method('getValueAsFloat')->willReturn(1500.75);
        $kpiValueMock->method('getPeriod')->willReturn(Period::fromString('2025-09'));

        $userMock = $this->createMock(User::class);
        $userMock->method('getId')->willReturn(300);
        $userMock->method('getEmail')->willReturn(EmailAddress::fromString('test@example.com'));

        $context = [
            'is_first_value' => true,
            'was_override' => false,
            'uploaded_files_count' => 2,
            'source' => 'manual_entry'
        ];
        
        $occurredOn = new \DateTimeImmutable('2025-09-04 14:20:30');

        // Act
        $event = new KPIValueAdded(
            $kpiMock,
            $kpiValueMock,
            $userMock,
            $occurredOn,
            $context
        );

        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertEquals('kpi_value_added_100_200_20250904142030', $array['event_id']);
        $this->assertEquals('kpi.value.added', $array['event_type']);
        $this->assertEquals('2025-09-04T14:20:30+00:00', $array['occurred_on']);
        $this->assertEquals(100, $array['kpi_id']);
        $this->assertEquals('Revenue KPI', $array['kpi_name']);
        $this->assertEquals('monthly', $array['kpi_interval']);
        $this->assertEquals(300, $array['user_id']);
        $this->assertEquals('test@example.com', $array['user_email']);
        $this->assertEquals(200, $array['value_id']);
        $this->assertEquals(1500.75, $array['value_amount']);
        $this->assertEquals('2025-09', $array['value_period']);
        $this->assertEquals($context, $array['context']);
        
        // Metadata assertions
        $this->assertTrue($array['metadata']['is_first_value']);
        $this->assertFalse($array['metadata']['was_overridden']);
        $this->assertTrue($array['metadata']['has_files']);
    }

    public function testToArrayHandlesNullPeriod(): void
    {
        // Arrange
        $kpiValueMock = $this->createMock(KPIValue::class);
        $kpiValueMock->method('getId')->willReturn(500);
        $kpiValueMock->method('getValueAsFloat')->willReturn(42.0);
        $kpiValueMock->method('getPeriod')->willReturn(null);

        $kpiMock = $this->createMock(KPI::class);
        $kpiMock->method('getId')->willReturn(600);
        $kpiMock->method('getName')->willReturn('Simple KPI');
        $kpiMock->method('getInterval')->willReturn(null);
        $kpiMock->method('getUser')->willReturn($this->user);

        // Act
        $event = new KPIValueAdded(
            $kpiMock,
            $kpiValueMock,
            $this->user,
            new \DateTimeImmutable()
        );

        $array = $event->toArray();

        // Assert
        $this->assertNull($array['value_period']);
        $this->assertNull($array['kpi_interval']);
    }

    public function testToArrayWithOverriddenValue(): void
    {
        // Arrange
        $overriddenValue = new KPIValue();
        $overriddenValue->setValue(DecimalValue::fromString('88.88'));

        $context = [
            'was_override' => true,
            'overridden_value' => $overriddenValue,
            'uploaded_files_count' => 0
        ];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        $array = $event->toArray();

        // Assert
        $this->assertTrue($array['metadata']['was_overridden']);
        $this->assertFalse($array['metadata']['has_files']);
        $this->assertEquals($context, $array['context']);
    }

    public function testToArrayMetadataConsistency(): void
    {
        // Arrange - Test that metadata matches actual method results
        $context = [
            'is_first_value' => false,
            'was_override' => true,
            'uploaded_files_count' => 1
        ];

        // Act
        $event = new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );

        $array = $event->toArray();

        // Assert - Metadata should match method results
        $this->assertEquals($event->isFirstValue(), $array['metadata']['is_first_value']);
        $this->assertEquals($event->wasOverridden(), $array['metadata']['was_overridden']);
        $this->assertEquals($event->hasFileUploads(), $array['metadata']['has_files']);
    }

    private function createEvent(array $context = []): KPIValueAdded
    {
        return new KPIValueAdded(
            $this->kpi,
            $this->kpiValue,
            $this->user,
            new \DateTimeImmutable(),
            $context
        );
    }
}
