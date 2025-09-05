<?php

namespace App\Tests\Domain\Event;

use App\Domain\Event\DomainEventRecorder;
use App\Domain\Event\KPICreated;
use App\Entity\KPI;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für DomainEventRecorder Trait.
 */
class DomainEventRecorderTest extends TestCase
{
    private TestEntity $entity;

    protected function setUp(): void
    {
        $this->entity = new TestEntity();
    }

    public function testRecordEventAddsEventToCollection(): void
    {
        // Arrange
        $event = $this->createMockEvent();

        // Act
        $this->entity->recordTestEvent($event);

        // Assert
        $this->assertTrue($this->entity->hasRecordedEvents());
        $this->assertCount(1, $this->entity->peekRecordedEvents());
        $this->assertSame($event, $this->entity->peekRecordedEvents()[0]);
    }

    public function testRecordMultipleEvents(): void
    {
        // Arrange
        $event1 = $this->createMockEvent();
        $event2 = $this->createMockEvent();

        // Act
        $this->entity->recordTestEvent($event1);
        $this->entity->recordTestEvent($event2);

        // Assert
        $this->assertCount(2, $this->entity->peekRecordedEvents());
        $this->assertEquals(2, $this->entity->getRecordedEventCount());
    }

    public function testGetRecordedEventsClearsCollection(): void
    {
        // Arrange
        $event = $this->createMockEvent();
        $this->entity->recordTestEvent($event);

        // Act
        $retrievedEvents = $this->entity->getRecordedEvents();

        // Assert
        $this->assertCount(1, $retrievedEvents);
        $this->assertSame($event, $retrievedEvents[0]);
        $this->assertFalse($this->entity->hasRecordedEvents());
        $this->assertCount(0, $this->entity->peekRecordedEvents());
    }

    public function testPeekRecordedEventsDoesNotClearCollection(): void
    {
        // Arrange
        $event = $this->createMockEvent();
        $this->entity->recordTestEvent($event);

        // Act
        $peekedEvents = $this->entity->peekRecordedEvents();

        // Assert
        $this->assertCount(1, $peekedEvents);
        $this->assertSame($event, $peekedEvents[0]);
        $this->assertTrue($this->entity->hasRecordedEvents());
        $this->assertCount(1, $this->entity->peekRecordedEvents());
    }

    public function testClearRecordedEventsRemovesAllEvents(): void
    {
        // Arrange
        $this->entity->recordTestEvent($this->createMockEvent());
        $this->entity->recordTestEvent($this->createMockEvent());

        // Act
        $this->entity->clearRecordedEvents();

        // Assert
        $this->assertFalse($this->entity->hasRecordedEvents());
        $this->assertCount(0, $this->entity->peekRecordedEvents());
        $this->assertEquals(0, $this->entity->getRecordedEventCount());
    }

    public function testHasRecordedEventsReturnsFalseWhenEmpty(): void
    {
        // Assert
        $this->assertFalse($this->entity->hasRecordedEvents());
    }

    public function testGetRecordedEventCountReturnsCorrectCount(): void
    {
        // Assert - Initially empty
        $this->assertEquals(0, $this->entity->getRecordedEventCount());

        // Act - Add events
        $this->entity->recordTestEvent($this->createMockEvent());
        $this->entity->recordTestEvent($this->createMockEvent());
        $this->entity->recordTestEvent($this->createMockEvent());

        // Assert - Count should be 3
        $this->assertEquals(3, $this->entity->getRecordedEventCount());
    }

    public function testEventsAreMaintainedInOrder(): void
    {
        // Arrange
        $event1 = $this->createMockEvent();
        $event2 = $this->createMockEvent();
        $event3 = $this->createMockEvent();

        // Act
        $this->entity->recordTestEvent($event1);
        $this->entity->recordTestEvent($event2);
        $this->entity->recordTestEvent($event3);

        // Assert
        $events = $this->entity->peekRecordedEvents();
        $this->assertSame($event1, $events[0]);
        $this->assertSame($event2, $events[1]);
        $this->assertSame($event3, $events[2]);
    }

    private function createMockEvent(): object
    {
        return new class {
            public string $type = 'test.event';
        };
    }
}

/**
 * Test-Entity die das DomainEventRecorder Trait nutzt.
 */
class TestEntity
{
    use DomainEventRecorder;

    public function recordTestEvent(object $event): void
    {
        $this->recordEvent($event);
    }
}
