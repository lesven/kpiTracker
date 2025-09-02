<?php

namespace App\Service;

use App\Entity\KPIFile;
use App\Entity\KPIValue;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Factory\KpiFileFactory;

/**
 * Service für Datei-Upload-Handling
 * User Story 5: Benutzer kann KPI-Werte mit Dateien erfassen.
 */
class FileUploadService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private KpiFileFactory $kpiFileFactory,
        private string $uploadDirectory = 'uploads/',
    ) {
    }

    /**
     * Verarbeitet Datei-Uploads für KPI-Werte.
     *
     * @param UploadedFile[]|null $uploadedFiles
     * @param KPIValue            $kpiValue
     *
     * @return array Statistiken über die Uploads
     */
    public function handleFileUploads(?array $uploadedFiles, KPIValue $kpiValue): array
    {
        $stats = [
            'uploaded' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if (empty($uploadedFiles)) {
            return $stats;
        }

        // Filter out null values and ensure we only have UploadedFile instances
        $validFiles = array_filter($uploadedFiles, function ($file) {
            return $file instanceof UploadedFile && $file->isValid() && UPLOAD_ERR_OK === $file->getError();
        });

        if (empty($validFiles)) {
            return $stats;
        }

        foreach ($validFiles as $uploadedFile) {
            try {
                // Datei validieren bevor Upload
                $validationErrors = $this->validateFile($uploadedFile);
                if (!empty($validationErrors)) {
                    ++$stats['failed'];
                    $stats['errors'][] = 'Datei "'.$uploadedFile->getClientOriginalName().'": '.implode(', ', $validationErrors);
                    continue;
                }

                $kpiFile = $this->kpiFileFactory->createFromUpload($uploadedFile, $kpiValue);
                $this->entityManager->persist($kpiFile);
                ++$stats['uploaded'];

                $this->logger->info('File uploaded successfully', [
                    'original_name' => $kpiFile->getOriginalName(),
                    'filename' => $kpiFile->getFilename(),
                    'kpi_value_id' => $kpiValue->getId(),
                ]);
            } catch (FileException $e) {
                ++$stats['failed'];
                $stats['errors'][] = 'Fehler beim Upload von "'.$uploadedFile->getClientOriginalName().'": '.$e->getMessage();

                $this->logger->error('File upload failed', [
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Löscht eine Datei physisch und aus der Datenbank.
     */
    public function deleteFile(KPIFile $kpiFile): bool
    {
        try {
            // Physische Datei löschen
            $filePath = $this->getUploadDirectory().$kpiFile->getFilename();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Aus Datenbank entfernen
            $this->entityManager->remove($kpiFile);

            $this->logger->info('File deleted successfully', [
                'filename' => $kpiFile->getFilename(),
                'original_name' => $kpiFile->getOriginalName(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('File deletion failed', [
                'filename' => $kpiFile->getFilename(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validiert eine hochgeladene Datei.
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Dateigröße prüfen (5MB Limit)
        if ($file->getSize() > 5 * 1024 * 1024) {
            $errors[] = 'Datei ist zu groß. Maximum: 5MB';
        }

        // MIME-Type prüfen
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'text/plain',
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            $errors[] = 'Dateityp nicht erlaubt: '.$file->getMimeType();
        }

        // Dateiname prüfen
        $originalName = $file->getClientOriginalName();
        if (mb_strlen($originalName) > 255) {
            $errors[] = 'Dateiname ist zu lang';
        }

        // Schädliche Endungen blockieren
        $dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js'];
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, $dangerousExtensions, true)) {
            $errors[] = 'Dateierweiterung nicht erlaubt: '.$extension;
        }

        return $errors;
    }

    /**
     * Gibt den absoluten Upload-Pfad zurück.
     */
    public function getUploadDirectory(): string
    {
        return __DIR__.'/../../public/'.rtrim($this->uploadDirectory, '/').'/';
    }

    /**
     * Gibt den relativen Upload-Pfad für URLs zurück.
     */
    public function getUploadUrl(): string
    {
        return '/'.rtrim($this->uploadDirectory, '/').'/';
    }

    /**
     * Generiert eine sichere Download-URL für eine Datei.
     */
    public function getFileUrl(KPIFile $kpiFile): string
    {
        return $this->getUploadUrl().$kpiFile->getFilename();
    }

    /**
     * Bereinigt verwaiste Dateien (Dateien ohne DB-Eintrag).
     */
    public function cleanupOrphanedFiles(): array
    {
        $stats = ['deleted' => 0, 'errors' => 0];

        try {
            $uploadPath = $this->getUploadDirectory();

            if (!is_dir($uploadPath)) {
                return $stats;
            }

            // Alle Dateinamen aus der DB laden
            $dbFiles = $this->entityManager
                ->getRepository(KPIFile::class)
                ->createQueryBuilder('f')
                ->select('f.filename')
                ->getQuery()
                ->getScalarResult();

            $dbFilenames = array_column($dbFiles, 'filename');

            // Alle physischen Dateien durchgehen
            $files = scandir($uploadPath);
            foreach ($files as $file) {
                if ('.' === $file || '..' === $file) {
                    continue;
                }

                // Wenn Datei nicht in DB existiert, löschen
                if (!in_array($file, $dbFilenames, true)) {
                    $filePath = $uploadPath.$file;
                    if (unlink($filePath)) {
                        ++$stats['deleted'];
                        $this->logger->info('Orphaned file deleted', ['filename' => $file]);
                    } else {
                        ++$stats['errors'];
                        $this->logger->error('Failed to delete orphaned file', ['filename' => $file]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Cleanup failed', ['error' => $e->getMessage()]);
            ++$stats['errors'];
        }

        return $stats;
    }
}
