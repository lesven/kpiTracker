<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service-Klasse für die Benutzerverwaltung und DSGVO-konforme Löschung.
 *
 * User Story 2: Administrator kann Benutzer anlegen und DSGVO-konform löschen.
 */
class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Löscht einen Benutzer DSGVO-konform mit allen zugehörigen Daten.
     *
     * @param User $user Der zu löschende Benutzer
     *
     * @throws \Exception Bei Fehlern während der Löschung
     */
    public function deleteUserWithData(User $user): void
    {
        $userEmail = $user->getEmail();
        $userId = $user->getId();

        $this->logger->info('Starting GDPR-compliant user deletion', [
            'user_id' => $userId,
            'user_email' => $userEmail,
        ]);

        try {
            $this->entityManager->beginTransaction();

            $stats = [
                'kpis_deleted' => 0,
                'values_deleted' => 0,
                'files_deleted' => 0,
            ];

            // 1. Alle KPIs des Benutzers laden und löschen
            $kpis = $user->getKpis();
            foreach ($kpis as $kpi) {
                ++$stats['kpis_deleted'];

                // 2. Alle KPI-Werte löschen (Cascade löscht auch Files)
                $values = $kpi->getValues();
                foreach ($values as $value) {
                    ++$stats['values_deleted'];

                    // 3. Dateien physisch vom Server löschen
                    $files = $value->getFiles();
                    foreach ($files as $file) {
                        ++$stats['files_deleted'];
                        $this->deletePhysicalFile($file->getFilename());
                    }
                }

                // KPI löschen (Cascade löscht Values und Files aus DB)
                $this->entityManager->remove($kpi);
            }

            // 4. Benutzer löschen
            $this->entityManager->remove($user);

            // 5. Änderungen in DB schreiben
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('GDPR-compliant user deletion completed', [
                'user_email' => $userEmail, // E-Mail für Audit-Log OK da bereits gelöscht
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('GDPR user deletion failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Fehler beim Löschen des Benutzers: '.$e->getMessage());
        }
    }

    /**
     * Löscht eine physische Datei vom Server.
     */
    private function deletePhysicalFile(string $filename): void
    {
        if (!$filename) {
            return;
        }

        $filePath = __DIR__.'/../../public/uploads/'.$filename;

        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                $this->logger->debug('Physical file deleted', ['file' => $filename]);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to delete physical file', [
                    'file' => $filename,
                    'error' => $e->getMessage(),
                ]);
                // Nicht kritisch - weiter machen
            }
        }
    }

    /**
     * Erstellt Statistiken über einen Benutzer vor der Löschung.
     */
    public function getUserDeletionStats(User $user): array
    {
        $stats = [
            'email' => $user->getEmail(),
            'created_at' => $user->getCreatedAt(),
            'kpi_count' => 0,
            'value_count' => 0,
            'file_count' => 0,
            'total_file_size' => 0,
        ];

        foreach ($user->getKpis() as $kpi) {
            ++$stats['kpi_count'];

            foreach ($kpi->getValues() as $value) {
                ++$stats['value_count'];

                foreach ($value->getFiles() as $file) {
                    ++$stats['file_count'];
                    $stats['total_file_size'] += $file->getFileSize() ?? 0;
                }
            }
        }

        return $stats;
    }

    /**
     * Validiert ob ein Benutzer gelöscht werden kann.
     */
    public function canDeleteUser(User $user, User $currentUser): array
    {
        $canDelete = true;
        $reasons = [];

        // Benutzer kann sich nicht selbst löschen
        if ($user === $currentUser) {
            $canDelete = false;
            $reasons[] = 'Ein Benutzer kann sich nicht selbst löschen.';
        }

        // Prüfen ob es der letzte Administrator ist
        if ($user->isAdmin()) {
            $adminCount = $this->entityManager
                ->getRepository(User::class)
                ->countAdmins();

            if ($adminCount <= 1) {
                $canDelete = false;
                $reasons[] = 'Der letzte Administrator kann nicht gelöscht werden.';
            }
        }

        return [
            'can_delete' => $canDelete,
            'reasons' => $reasons,
        ];
    }
}
