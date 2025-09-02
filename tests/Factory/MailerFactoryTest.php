<?php

namespace App\Tests\Factory;

use App\Entity\MailSettings;
use App\Domain\ValueObject\EmailAddress;
use App\Factory\MailerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;

class MailerFactoryTest extends TestCase
{
    private MailerInterface $defaultMailer;
    private MailerFactory $factory;

    protected function setUp(): void
    {
        $this->defaultMailer = $this->createMock(MailerInterface::class);
        $this->factory = new MailerFactory($this->defaultMailer);
    }

    public function testCreateDefault(): void
    {
        $result = $this->factory->createDefault();

        $this->assertSame($this->defaultMailer, $result);
    }

    public function testCreateFromSettings(): void
    {
        $settings = $this->createMailSettings('smtp.example.com', 587, 'user@example.com', 'password');

        $mailer = $this->factory->createFromSettings($settings);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertNotSame($this->defaultMailer, $mailer);
    }

    public function testCreateFromSettingsWithoutCredentials(): void
    {
        $settings = $this->createMailSettings('smtp.example.com', 587);

        $mailer = $this->factory->createFromSettings($settings);

        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    public function testCreateFromSettingsWithIgnoreCertificate(): void
    {
        $settings = $this->createMailSettings('smtp.example.com', 587, 'user@example.com', 'password', true);

        // Erwarte eine PHP User Warning wegen ignorierter Zertifikate
        $errorTriggered = false;
        set_error_handler(function ($severity, $message) use (&$errorTriggered) {
            if ($severity === E_USER_WARNING && str_contains($message, 'Ignoring certificate validation')) {
                $errorTriggered = true;
            }
            return true;
        });

        $mailer = $this->factory->createFromSettings($settings);

        restore_error_handler();

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertTrue($errorTriggered, 'Expected certificate warning was not triggered');
    }

    public function testCreateWithParameters(): void
    {
        $mailer = $this->factory->createWithParameters('smtp.test.com', 465, 'test@test.com', 'secret');

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertNotSame($this->defaultMailer, $mailer);
    }

    public function testCreateWithParametersWithoutCredentials(): void
    {
        $mailer = $this->factory->createWithParameters('smtp.test.com', 465);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertNotSame($this->defaultMailer, $mailer);
    }

    public function testCreateWithParametersIgnoringCertificate(): void
    {
        // Erwarte eine PHP User Warning wegen ignorierter Zertifikate
        $errorTriggered = false;
        set_error_handler(function ($severity, $message) use (&$errorTriggered) {
            if ($severity === E_USER_WARNING && str_contains($message, 'Ignoring certificate validation')) {
                $errorTriggered = true;
            }
            return true;
        });

        $mailer = $this->factory->createWithParameters(
            'smtp.test.com',
            465,
            'test@test.com',
            'secret',
            true
        );

        restore_error_handler();

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertTrue($errorTriggered, 'Expected certificate warning was not triggered');
    }

    public function testCreateWithDifferentPorts(): void
    {
        $mailer587 = $this->factory->createWithParameters('smtp.test.com', 587);
        $mailer465 = $this->factory->createWithParameters('smtp.test.com', 465);
        $mailer25 = $this->factory->createWithParameters('smtp.test.com', 25);

        $this->assertInstanceOf(Mailer::class, $mailer587);
        $this->assertInstanceOf(Mailer::class, $mailer465);
        $this->assertInstanceOf(Mailer::class, $mailer25);
    }

    public function testCreateWithSpecialCharactersInCredentials(): void
    {
        $username = 'user+special@example.com';
        $password = 'p@ssw0rd!#$%';

        $mailer = $this->factory->createWithParameters('smtp.test.com', 587, $username, $password);

        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    public function testMultipleMailersAreIndependent(): void
    {
        $mailer1 = $this->factory->createWithParameters('smtp1.test.com', 587);
        $mailer2 = $this->factory->createWithParameters('smtp2.test.com', 465);
        $defaultMailer = $this->factory->createDefault();

        $this->assertNotSame($mailer1, $mailer2);
        $this->assertNotSame($mailer1, $defaultMailer);
        $this->assertNotSame($mailer2, $defaultMailer);
        $this->assertSame($this->defaultMailer, $defaultMailer);
    }

    /**
     * Hilfsmethode zum Erstellen von MailSettings Mock-Objekten.
     */
    private function createMailSettings(
        string $host,
        int $port,
        ?string $username = null,
        ?string $password = null,
        bool $ignoreCertificate = false
    ): MailSettings {
        $settings = $this->createMock(MailSettings::class);
        $settings->method('getHost')->willReturn($host);
        $settings->method('getPort')->willReturn($port);
        $settings->method('getPassword')->willReturn($password);
        $settings->method('isIgnoreCertificate')->willReturn($ignoreCertificate);

        if ($username) {
            // EmailAddress ist final, daher erstellen wir eine echte Instanz
            $emailAddress = new EmailAddress($username);
            $settings->method('getUsername')->willReturn($emailAddress);
        } else {
            $settings->method('getUsername')->willReturn(null);
        }

        return $settings;
    }
}