<?php

namespace App\Service;

use App\Entity\KPI;
use App\Entity\MailSettings;
use App\Entity\User;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Repository\MailSettingsRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service-Klasse mit Hilfsfunktionen für Administratoren.
 *
 * Bietet Methoden für Benutzer-, KPI- und Mailserververwaltung sowie Dashboard-Statistiken.
 */
class AdminService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private KPIRepository $kpiRepository,
        private KPIValueRepository $kpiValueRepository,
        private MailSettingsRepository $mailSettingsRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ReminderService $reminderService,
    ) {
    }

    /**
     * Erstellt Statistikdaten für das Admin-Dashboard.
     */
    public function getDashboardStats(): array
    {
        return [
            'total_users' => $this->userRepository->countUsers(),
            'total_admins' => $this->userRepository->countAdmins(),
            'total_kpis' => $this->kpiRepository->countAll(),
            'recent_users' => $this->userRepository->findCreatedBetween(
                new \DateTimeImmutable('-30 days'),
                new \DateTimeImmutable(),
            ),
            'kpis_by_user' => $this->kpiRepository->countKpisByUser(),
        ];
    }

    /**
     * Erstellt einen neuen Benutzer mit gehashtem Passwort.
     */
    public function createUser(User $user, string $plainPassword): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Aktualisiert einen Benutzer. Optional kann ein neues Passwort gesetzt werden.
     */
    public function updateUser(User $user, ?string $plainPassword = null): void
    {
        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();
    }

    /**
     * Liefert alle KPIs mit den jeweils letzten Werten.
     *
     * @return array{0: KPI[], 1: array<int, mixed>}
     */
    public function getKpisWithLastValues(): array
    {
        $kpis = $this->kpiRepository->findAllWithUser();
        $lastValues = [];

        foreach ($kpis as $kpi) {
            $lastValues[$kpi->getId()] = $this->kpiValueRepository->findLatestValueForKpi($kpi);
        }

        return [$kpis, $lastValues];
    }

    /**
     * Speichert E-Mail-Einstellungen.
     */
    public function saveMailSettings(MailSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    /**
     * Sendet eine Test-E-Mail über das Reminder-System.
     */
    public function sendTestReminder(string $email): bool
    {
        return $this->reminderService->sendTestEmail($email);
    }

    /**
     * Sendet alle fälligen Erinnerungen und gibt Statistik zurück.
     */
    public function sendAllReminders(): array
    {
        return $this->reminderService->sendDueReminders();
    }

    /**
     * Gibt vorhandene E-Mail-Einstellungen zurück oder erstellt neue.
     */
    public function getMailSettings(): MailSettings
    {
        return $this->mailSettingsRepository->findOneBy([]) ?? new MailSettings();
    }
}
