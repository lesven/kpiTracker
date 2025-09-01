<?php

namespace App\Tests\Service;

use App\Repository\MailSettingsRepository;
use App\Service\ConfigurableMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ConfigurableMailerTest extends TestCase
{
    public function testSendUsesDefaultMailerIfNoSettings(): void
    {
        $settingsRepo = $this->createMock(MailSettingsRepository::class);
        $defaultMailer = $this->createMock(MailerInterface::class);
        $email = $this->createMock(Email::class);

        $settingsRepo->method('findOneBy')->willReturn(null);
        $defaultMailer->expects($this->once())
            ->method('send')
            ->with($email);

        $mailer = new ConfigurableMailer($settingsRepo, $defaultMailer);
        $mailer->send($email);
    }

    public function testSendUsesDefaultSettingsWhenNoneConfigured(): void
    {
        $settingsRepo = $this->createMock(MailSettingsRepository::class);
        $defaultMailer = $this->createMock(MailerInterface::class);
        $email = $this->createMock(Email::class);

        // Zuerst null für isDefault = true, dann null für alle Settings
        $settingsRepo->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                // Simuliere aufeinanderfolgende Aufrufe ohne withConsecutive
                return null;
            });

        $defaultMailer->expects($this->once())
            ->method('send')
            ->with($email);

        $mailer = new ConfigurableMailer($settingsRepo, $defaultMailer);
        $mailer->send($email);
    }
}
