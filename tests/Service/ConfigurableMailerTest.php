<?php

namespace App\Tests\Service;

use App\Entity\MailSettings;
use App\Factory\MailerFactory;
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
        $mailerFactory = $this->createMock(MailerFactory::class);
        $email = $this->createMock(Email::class);

        $settingsRepo->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, null);
        
        $mailerFactory->expects($this->once())
            ->method('createDefault')
            ->willReturn($defaultMailer);
        
        $defaultMailer->expects($this->once())
            ->method('send')
            ->with($email);

        $mailer = new ConfigurableMailer($settingsRepo, $mailerFactory);
        $mailer->send($email);
    }

    public function testSendUsesDefaultSettingsWhenAvailable(): void
    {
        $settingsRepo = $this->createMock(MailSettingsRepository::class);
        $configuredMailer = $this->createMock(MailerInterface::class);
        $mailerFactory = $this->createMock(MailerFactory::class);
        $email = $this->createMock(Email::class);
        $settings = $this->createMock(MailSettings::class);

        $settingsRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['isDefault' => true])
            ->willReturn($settings);

        $mailerFactory->expects($this->once())
            ->method('createFromSettings')
            ->with($settings)
            ->willReturn($configuredMailer);
        
        $configuredMailer->expects($this->once())
            ->method('send')
            ->with($email);

        $mailer = new ConfigurableMailer($settingsRepo, $mailerFactory);
        $mailer->send($email);
    }

    public function testSendUsesFirstAvailableSettingsWhenNoDefault(): void
    {
        $settingsRepo = $this->createMock(MailSettingsRepository::class);
        $configuredMailer = $this->createMock(MailerInterface::class);
        $mailerFactory = $this->createMock(MailerFactory::class);
        $email = $this->createMock(Email::class);
        $settings = $this->createMock(MailSettings::class);

        $settingsRepo->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, $settings);

        $mailerFactory->expects($this->once())
            ->method('createFromSettings')
            ->with($settings)
            ->willReturn($configuredMailer);
        
        $configuredMailer->expects($this->once())
            ->method('send')
            ->with($email);

        $mailer = new ConfigurableMailer($settingsRepo, $mailerFactory);
        $mailer->send($email);
    }

    public function testSendHandlesMailerExceptions(): void
    {
        $settingsRepo = $this->createMock(MailSettingsRepository::class);
        $defaultMailer = $this->createMock(MailerInterface::class);
        $mailerFactory = $this->createMock(MailerFactory::class);
        $email = $this->createMock(Email::class);

        $settingsRepo->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, null);

        $mailerFactory->expects($this->once())
            ->method('createDefault')
            ->willReturn($defaultMailer);

        $defaultMailer->expects($this->once())
            ->method('send')
            ->with($email)
            ->willThrowException(new \Exception('Mail sending failed'));

        $mailer = new ConfigurableMailer($settingsRepo, $mailerFactory);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Mail sending failed');

        $mailer->send($email);
    }
}
