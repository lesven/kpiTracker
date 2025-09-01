<?php

namespace App\Tests\Service;

use App\Service\ReminderService;
use App\Service\ConfigurableMailer;
use App\Service\KPIStatusService;
use App\Repository\KPIRepository;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

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
}
