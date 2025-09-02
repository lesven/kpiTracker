<?php

namespace App\Tests\Factory;

use App\Factory\ReminderEmailFactory;
use App\Entity\User;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Symfony\Component\Mime\Email;

class ReminderEmailFactoryTest extends TestCase
{
    public function testCreateUpcomingReminderGeneratesEmail(): void
    {
        $twig = $this->createMock(Environment::class);
        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $twig->method('render')->willReturn('<html></html>');
        $urlGen->method('generate')->willReturn('http://example.com');

        $factory = new ReminderEmailFactory($twig, $urlGen, 'from@example.com');
        $user = (new User())->setEmail(new EmailAddress('user@example.com'));

        $email = $factory->createUpcomingReminder($user, []);

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('KPI-Erinnerung: Fällige Einträge in 3 Tagen', $email->getSubject());
    }

    public function testCreateTestEmail(): void
    {
        $twig = $this->createMock(Environment::class);
        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $twig->method('render')->willReturn('<html></html>');
        $urlGen->method('generate')->willReturn('http://example.com');

        $factory = new ReminderEmailFactory($twig, $urlGen, 'from@example.com');
        $email = $factory->createTestEmail('recipient@example.com');

        $this->assertSame('recipient@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('KPI-Tracker: Test-E-Mail', $email->getSubject());
    }
}
