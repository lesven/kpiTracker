<?php

namespace App\Tests\Factory;

use App\Entity\User;
use App\Factory\ReminderEmailFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Unit-Tests für {@see ReminderEmailFactory}.
 */
class ReminderEmailFactoryTest extends TestCase
{
    /** @var Environment&MockObject */
    private Environment $twig;

    /** @var UrlGeneratorInterface&MockObject */
    private UrlGeneratorInterface $urlGenerator;

    private ReminderEmailFactory $factory;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->factory = new ReminderEmailFactory(
            $this->twig,
            $this->urlGenerator,
            'from@example.com'
        );
    }

    #[Test]
    public function createUpcomingRendersTemplateAndBuildsEmail(): void
    {
        $user = (new User())->setEmail('user@example.com');
        $reminders = ['foo'];

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://dashboard');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                'emails/upcoming_reminder.html.twig',
                $this->callback(fn (array $context) => $context['user'] === $user
                    && $context['reminders'] === $reminders
                    && 'http://dashboard' === $context['dashboard_url'])
            )
            ->willReturn('<html>upcoming</html>');

        $email = $this->factory->createUpcoming($user, $reminders);

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('KPI-Erinnerung: Fällige Einträge in 3 Tagen', $email->getSubject());
        $this->assertSame('<html>upcoming</html>', $email->getHtmlBody());
    }

    #[Test]
    public function createDueTodayRendersTemplateAndBuildsEmail(): void
    {
        $user = (new User())->setEmail('user@example.com');
        $reminders = ['bar'];

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://dashboard');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                'emails/due_today_reminder.html.twig',
                $this->callback(fn (array $context) => $context['user'] === $user
                    && $context['reminders'] === $reminders
                    && 'http://dashboard' === $context['dashboard_url'])
            )
            ->willReturn('<html>today</html>');

        $email = $this->factory->createDueToday($user, $reminders);

        $this->assertSame('KPI-Erinnerung: Einträge sind heute fällig', $email->getSubject());
        $this->assertSame('<html>today</html>', $email->getHtmlBody());
    }

    #[Test]
    public function createOverdueIncludesUrgencyAndDays(): void
    {
        $user = (new User())->setEmail('user@example.com');
        $reminders = ['baz'];

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://dashboard');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                'emails/overdue_reminder.html.twig',
                $this->callback(fn (array $context) => $context['user'] === $user
                    && $context['reminders'] === $reminders
                    && 14 === $context['days_overdue']
                    && 'high' === $context['urgency_level']
                    && 'http://dashboard' === $context['dashboard_url'])
            )
            ->willReturn('<html>overdue</html>');

        $email = $this->factory->createOverdue($user, $reminders, 14);

        $this->assertSame('DRINGEND: KPI-Einträge sind seit 14 Tagen überfällig', $email->getSubject());
        $this->assertSame('<html>overdue</html>', $email->getHtmlBody());
    }

    #[Test]
    public function createEscalationSendsToAdmin(): void
    {
        $admin = (new User())->setEmail('admin@example.com');
        $user = (new User())->setEmail('user@example.com');
        $reminders = ['abc'];

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_admin_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://admin');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                'emails/escalation_to_admin.html.twig',
                $this->callback(fn (array $context) => $context['admin'] === $admin
                    && $context['user'] === $user
                    && $context['reminders'] === $reminders
                    && 21 === $context['days_overdue']
                    && 'http://admin' === $context['admin_url'])
            )
            ->willReturn('<html>escalation</html>');

        $email = $this->factory->createEscalation($admin, $user, $reminders);

        $this->assertSame('admin@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('ESKALATION: KPI-Einträge seit 21 Tagen überfällig', $email->getSubject());
        $this->assertSame('<html>escalation</html>', $email->getHtmlBody());
    }

    #[Test]
    public function createTestEmail(): void
    {
        $this->urlGenerator->expects($this->never())->method('generate');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                'emails/test_email.html.twig',
                $this->callback(fn (array $context) => 'test@example.com' === $context['recipient']
                    && $context['timestamp'] instanceof \DateTimeImmutable)
            )
            ->willReturn('<html>test</html>');

        $email = $this->factory->createTest('test@example.com');

        $this->assertSame('test@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('KPI-Tracker: Test-E-Mail', $email->getSubject());
        $this->assertSame('<html>test</html>', $email->getHtmlBody());
    }
}
