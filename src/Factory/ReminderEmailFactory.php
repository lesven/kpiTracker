<?php

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Factory zum Erstellen von E-Mails für KPI-Erinnerungen.
 */
class ReminderEmailFactory
{
    /**
     * @param Environment           $twig         Twig-Umgebung zum Rendern der HTML-Vorlagen
     * @param UrlGeneratorInterface $urlGenerator Generiert absolute URLs für Links in den E-Mails
     * @param string                $fromEmail    Standard-Absenderadresse
     */
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail = 'noreply@kpi-tracker.local',
    ) {
    }

    /**
     * Erstellt eine Erinnerung für bevorstehende KPI-Einträge (in 3 Tagen fällig).
     *
     * @param User  $user      Empfänger der E-Mail
     * @param array $reminders Liste der fälligen KPIs
     *
     * @return Email
     */
    public function createUpcoming(User $user, array $reminders): Email
    {
        return (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('KPI-Erinnerung: Fällige Einträge in 3 Tagen')
            ->html($this->twig->render('emails/upcoming_reminder.html.twig', [
                'user' => $user,
                'reminders' => $reminders,
                'dashboard_url' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Erstellt eine Erinnerung für KPI-Einträge, die heute fällig sind.
     *
     * @param User  $user      Empfänger der E-Mail
     * @param array $reminders Liste der fälligen KPIs
     *
     * @return Email
     */
    public function createDueToday(User $user, array $reminders): Email
    {
        return (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('KPI-Erinnerung: Einträge sind heute fällig')
            ->html($this->twig->render('emails/due_today_reminder.html.twig', [
                'user' => $user,
                'reminders' => $reminders,
                'dashboard_url' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Erstellt eine Erinnerung für überfällige KPI-Einträge.
     *
     * @param User  $user        Empfänger der E-Mail
     * @param array $reminders   Liste der überfälligen KPIs
     * @param int   $daysOverdue Anzahl der Tage, die die KPIs überfällig sind
     *
     * @return Email
     */
    public function createOverdue(User $user, array $reminders, int $daysOverdue): Email
    {
        $urgencyLevel = match ($daysOverdue) {
            7 => 'medium',
            14 => 'high',
            default => 'low',
        };

        return (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
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
     * Erstellt eine Eskalations-E-Mail an den Administrator, wenn KPIs überfällig sind.
     *
     * @param User  $admin       Administrator, der die E-Mail erhält
     * @param User  $user        Benutzer, dessen KPIs überfällig sind
     * @param array $reminders   Liste der überfälligen KPIs
     * @param int   $daysOverdue Anzahl der überfälligen Tage (Standard 21)
     *
     * @return Email
     */
    public function createEscalation(User $admin, User $user, array $reminders, int $daysOverdue = 21): Email
    {
        return (new Email())
            ->from($this->fromEmail)
            ->to($admin->getEmail())
            ->subject('ESKALATION: KPI-Einträge seit 21 Tagen überfällig')
            ->html($this->twig->render('emails/escalation_to_admin.html.twig', [
                'admin' => $admin,
                'user' => $user,
                'reminders' => $reminders,
                'days_overdue' => $daysOverdue,
                'admin_url' => $this->urlGenerator->generate('app_admin_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * Erstellt eine Test-E-Mail, um die Konfiguration zu prüfen.
     *
     * @param string $recipientEmail Empfänger der Test-E-Mail
     *
     * @return Email
     */
    public function createTest(string $recipientEmail): Email
    {
        return (new Email())
            ->from($this->fromEmail)
            ->to($recipientEmail)
            ->subject('KPI-Tracker: Test-E-Mail')
            ->html($this->twig->render('emails/test_email.html.twig', [
                'recipient' => $recipientEmail,
                'timestamp' => new \DateTimeImmutable(),
            ]));
    }
}
