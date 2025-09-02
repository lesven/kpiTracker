<?php

namespace App\Tests\Service;

use App\Domain\ValueObject\EmailAddress;
use App\Factory\ReminderEmailFactory;
use App\Repository\KPIRepository;
use App\Service\ConfigurableMailer;
use App\Service\KPIStatusService;
use App\Service\ReminderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReminderServiceTest extends TestCase
{
    public function testSendDueRemindersReturnsArray(): void
    {
        $mailer = $this->createMock(ConfigurableMailer::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $factory = $this->createMock(ReminderEmailFactory::class);
        $kpiRepo->method('findDueForReminder')->willReturn([]);
        $service = new ReminderService($mailer, $statusService, $kpiRepo, $logger, $factory);
        $result = $service->sendDueReminders();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('escalations', $result);
    }

    public function testSendTestEmailReturnsBool(): void
    {
        $mailer = $this->createMock(ConfigurableMailer::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $factory = $this->createMock(ReminderEmailFactory::class);
        $factory->method('createTestEmail')->willReturn(new \Symfony\Component\Mime\Email());

        $service = new ReminderService($mailer, $statusService, $kpiRepo, $logger, $factory);
        $result = $service->sendTestEmail('test@example.com');

        $this->assertIsBool($result);
    }

    public function testSendDueRemindersWithDueKpis(): void
    {
        $mailer = $this->createMock(ConfigurableMailer::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $factory = $this->createMock(ReminderEmailFactory::class);

        $kpi = $this->createMock(\App\Entity\KPI::class);
        $user = $this->createMock(\App\Entity\User::class);

        $kpi->method('getUser')->willReturn($user);
        $user->method('getEmail')->willReturn(new EmailAddress('user@example.com'));
        $kpiRepo->method('findDueForReminder')->willReturn([$kpi]);

        $service = new ReminderService($mailer, $statusService, $kpiRepo, $logger, $factory);
        $result = $service->sendDueReminders();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('skipped', $result);
    }
}
