<?php

namespace App\Tests\Service;

use App\Repository\KPIRepository;
use App\Service\ConfigurableMailer;
use App\Service\KPIStatusService;
use App\Service\ReminderService;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ReminderServiceTest extends TestCase
{
    public function testSendDueRemindersReturnsArray(): void
    {
        $mailer = $this->createMock(ConfigurableMailer::class);
        $twig = $this->createMock(Environment::class);
        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $kpiRepo->method('findDueForReminder')->willReturn([]);
        $service = new ReminderService($mailer, $twig, $urlGen, $statusService, $kpiRepo, $logger, 'noreply@kpi-tracker.local');
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
        $twig = $this->createMock(Environment::class);
        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        // ConfigurableMailer::send() return void, nicht bool
        $twig->method('render')->willReturn('<html>Test</html>');

        $service = new ReminderService($mailer, $twig, $urlGen, $statusService, $kpiRepo, $logger, 'noreply@kpi-tracker.local');
        $result = $service->sendTestEmail('test@example.com');

        $this->assertIsBool($result);
    }

    public function testSendDueRemindersWithDueKpis(): void
    {
        $mailer = $this->createMock(ConfigurableMailer::class);
        $twig = $this->createMock(Environment::class);
        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $statusService = $this->createMock(KPIStatusService::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $kpi = $this->createMock(\App\Entity\KPI::class);
        $user = $this->createMock(\App\Entity\User::class);

        $kpi->method('getUser')->willReturn($user);
        $user->method('getEmail')->willReturn(new EmailAddress('user@example.com'));
        $kpiRepo->method('findDueForReminder')->willReturn([$kpi]);

        $service = new ReminderService($mailer, $twig, $urlGen, $statusService, $kpiRepo, $logger, 'noreply@kpi-tracker.local');
        $result = $service->sendDueReminders();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('skipped', $result);
    }
}
