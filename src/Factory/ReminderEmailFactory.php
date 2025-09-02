<?php

namespace App\Factory;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\User;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Factory for creating reminder related emails.
 */
class ReminderEmailFactory
{
    private EmailAddress $fromEmailAddress;

    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        string $fromEmail = 'noreply@kpi-tracker.local',
    ) {
        $this->fromEmailAddress = new EmailAddress($fromEmail);
    }

    /**
     * Creates an email reminding a user about upcoming KPIs due in three days.
     *
     * @param User  $user      Recipient user
     * @param array $reminders List of KPI reminders
     */
    public function createUpcomingReminder(User $user, array $reminders): Email
    {
        return (new Email())
            ->from($this->fromEmailAddress->getValue())
            ->to($user->getEmail()->getValue())
            ->subject('KPI-Erinnerung: Fällige Einträge in 3 Tagen')
            ->html($this->twig->render('emails/upcoming_reminder.html.twig', [
                'user' => $user,
                'reminders' => $reminders,
                'dashboard_url' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Creates an email for KPIs that are due today.
     *
     * @param User  $user      Recipient user
     * @param array $reminders List of KPI reminders
     */
    public function createDueTodayReminder(User $user, array $reminders): Email
    {
        return (new Email())
            ->from($this->fromEmailAddress->getValue())
            ->to($user->getEmail()->getValue())
            ->subject('KPI-Erinnerung: Einträge sind heute fällig')
            ->html($this->twig->render('emails/due_today_reminder.html.twig', [
                'user' => $user,
                'reminders' => $reminders,
                'dashboard_url' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Creates an email for overdue KPIs.
     *
     * @param User  $user         Recipient user
     * @param array $reminders    List of KPI reminders
     * @param int   $daysOverdue  How many days the KPIs are overdue
     */
    public function createOverdueReminder(User $user, array $reminders, int $daysOverdue): Email
    {
        $urgencyLevel = match ($daysOverdue) {
            7 => 'medium',
            14 => 'high',
            default => 'low',
        };

        return (new Email())
            ->from($this->fromEmailAddress->getValue())
            ->to($user->getEmail()->getValue())
            ->subject("DRINGEND: KPI-Einträge sind seit {$daysOverdue} Tagen überfällig")
            ->html($this->twig->render('emails/overdue_reminder.html.twig', [
                'user' => $user,
                'reminders' => $reminders,
                'days_overdue' => $daysOverdue,
                'urgency_level' => $urgencyLevel,
                'dashboard_url' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Creates an escalation email to an administrator.
     *
     * @param User  $admin     Administrator receiving the escalation
     * @param User  $user      User with overdue KPIs
     * @param array $reminders List of KPI reminders
     */
    public function createEscalation(User $admin, User $user, array $reminders): Email
    {
        return (new Email())
            ->from($this->fromEmailAddress->getValue())
            ->to($admin->getEmail()->getValue())
            ->subject('ESKALATION: KPI-Einträge seit 21 Tagen überfällig')
            ->html($this->twig->render('emails/escalation_to_admin.html.twig', [
                'admin' => $admin,
                'user' => $user,
                'reminders' => $reminders,
                'days_overdue' => 21,
                'admin_url' => $this->urlGenerator->generate('app_admin_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Creates a simple test email to verify mail configuration.
     */
    public function createTestEmail(string $recipient): Email
    {
        return (new Email())
            ->from($this->fromEmailAddress->getValue())
            ->to($recipient)
            ->subject('KPI-Tracker: Test-E-Mail')
            ->html($this->twig->render('emails/test_email.html.twig', [
                'recipient' => $recipient,
                'timestamp' => new \DateTimeImmutable(),
            ]));
    }
}
