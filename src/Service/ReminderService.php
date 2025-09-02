<?php

namespace App\Service;

use App\Entity\KPI;
use App\Entity\User;
use App\Repository\KPIRepository;
use Psr\Log\LoggerInterface;
use App\Factory\ReminderEmailFactory;

/**
 * Service-Klasse für das Versenden von E-Mail-Erinnerungen und Eskalationen.
 *
 * User Stories 6, 7: Reminder für fällige KPI-Einträge und Eskalation an Administratoren.
 */
class ReminderService
{
    public function __construct(
        private readonly ConfigurableMailer $mailer,
        private readonly KPIStatusService $kpiStatusService,
        private readonly KPIRepository $kpiRepository,
        private readonly LoggerInterface $logger,
        private readonly ReminderEmailFactory $emailFactory,
    ) {
    }

    /**
     * Sendet alle fälligen Erinnerungen
     * User Story 6: Reminder für fällige KPI-Einträge.
     *
     * @return array Statistiken über gesendete E-Mails
     */
    public function sendDueReminders(): array
    {
        $stats = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'escalations' => 0,
        ];

        // Alle KPIs für Erinnerungsverarbeitung laden
        $allKpis = $this->kpiRepository->findDueForReminder();

        // Nach Benutzern gruppieren für effiziente E-Mail-Verarbeitung
        $kpisByUser = [];
        foreach ($allKpis as $kpi) {
            $userId = $kpi->getUser()->getId();
            if (!isset($kpisByUser[$userId])) {
                $kpisByUser[$userId] = [
                    'user' => $kpi->getUser(),
                    'kpis' => [],
                ];
            }
            $kpisByUser[$userId]['kpis'][] = $kpi;
        }

        foreach ($kpisByUser as $userGroup) {
            $user = $userGroup['user'];
            $userKpis = $userGroup['kpis'];

            // Verschiedene Erinnerungstypen sammeln
            $upcomingReminders = $this->kpiStatusService->getKpisForReminder($userKpis, 3, 0);
            $dueTodayReminders = $this->kpiStatusService->getKpisForReminder($userKpis, 0, 0);
            $overdueReminders7 = $this->kpiStatusService->getKpisForReminder($userKpis, 0, 7);
            $overdueReminders14 = $this->kpiStatusService->getKpisForReminder($userKpis, 0, 14);
            $escalationReminders = $this->kpiStatusService->getKpisForReminder($userKpis, 0, 21);

            // Vorab-Erinnerungen (3 Tage vor Fälligkeit)
            if (!empty($upcomingReminders)) {
                $result = $this->sendUpcomingReminder($user, $upcomingReminders);
                $stats['sent'] += $result ? 1 : 0;
                $stats['failed'] += $result ? 0 : 1;
            }

            // Erinnerungen für heute fällige KPIs
            if (!empty($dueTodayReminders)) {
                $result = $this->sendDueTodayReminder($user, $dueTodayReminders);
                $stats['sent'] += $result ? 1 : 0;
                $stats['failed'] += $result ? 0 : 1;
            }

            // Überfällige Erinnerungen (7 Tage)
            if (!empty($overdueReminders7)) {
                $result = $this->sendOverdueReminder($user, $overdueReminders7, 7);
                $stats['sent'] += $result ? 1 : 0;
                $stats['failed'] += $result ? 0 : 1;
            }

            // Überfällige Erinnerungen (14 Tage)
            if (!empty($overdueReminders14)) {
                $result = $this->sendOverdueReminder($user, $overdueReminders14, 14);
                $stats['sent'] += $result ? 1 : 0;
                $stats['failed'] += $result ? 0 : 1;
            }

            // Eskalation an Admins (21 Tage)
            if (!empty($escalationReminders)) {
                $result = $this->sendEscalationToAdmins($user, $escalationReminders);
                $stats['escalations'] += $result ? 1 : 0;
                $stats['failed'] += $result ? 0 : 1;
            }
        }

        $this->logger->info('Reminder batch completed', $stats);

        return $stats;
    }

    /**
     * Sendet Vorab-Erinnerung (3 Tage vor Fälligkeit).
     */
    private function sendUpcomingReminder(User $user, array $reminders): bool
    {
        try {
            $email = $this->emailFactory->createUpcomingReminder($user, $reminders);
            $this->mailer->send($email);

            $this->logger->info('Upcoming reminder sent', [
                'user' => $user->getEmail()->getValue(),
                'kpi_count' => count($reminders),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send upcoming reminder', [
                'user' => $user->getEmail()->getValue(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sendet Erinnerung für heute fällige KPIs.
     */
    private function sendDueTodayReminder(User $user, array $reminders): bool
    {
        try {
            $email = $this->emailFactory->createDueTodayReminder($user, $reminders);
            $this->mailer->send($email);

            $this->logger->info('Due today reminder sent', [
                'user' => $user->getEmail()->getValue(),
                'kpi_count' => count($reminders),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send due today reminder', [
                'user' => $user->getEmail()->getValue(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sendet Erinnerung für überfällige KPIs.
     */
    private function sendOverdueReminder(User $user, array $reminders, int $daysOverdue): bool
    {
        try {
            $email = $this->emailFactory->createOverdueReminder($user, $reminders, $daysOverdue);
            $this->mailer->send($email);

            $this->logger->info('Overdue reminder sent', [
                'user' => $user->getEmail()->getValue(),
                'days_overdue' => $daysOverdue,
                'kpi_count' => count($reminders),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send overdue reminder', [
                'user' => $user->getEmail()->getValue(),
                'days_overdue' => $daysOverdue,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sendet Eskalation an alle Administratoren
     * User Story 7: Eskalation bei fehlender Eintragung.
     */
    private function sendEscalationToAdmins(User $user, array $reminders): bool
    {
        try {
            // Alle Administratoren finden
            $admins = $this->kpiRepository->getEntityManager()
                ->getRepository(User::class)
                ->findAdmins();

            if (empty($admins)) {
                $this->logger->warning('No admins found for escalation', [
                    'user' => $user->getEmail()->getValue(),
                ]);

                return false;
            }

            foreach ($admins as $admin) {
                $email = $this->emailFactory->createEscalation($admin, $user, $reminders);
                $this->mailer->send($email);
            }

            $this->logger->warning('Escalation sent to admins', [
                'user' => $user->getEmail()->getValue(),
                'admin_count' => count($admins),
                'kpi_count' => count($reminders),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send escalation to admins', [
                'user' => $user->getEmail()->getValue(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sendet Test-E-Mail an angegebene Adresse.
     */
    public function sendTestEmail(string $recipientEmail): bool
    {
        try {
            $email = $this->emailFactory->createTestEmail($recipientEmail);
            $this->mailer->send($email);

            $this->logger->info('Test email sent', [
                'recipient' => $recipientEmail,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send test email', [
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
