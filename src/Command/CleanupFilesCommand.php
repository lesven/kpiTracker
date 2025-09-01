<?php

namespace App\Command;

use App\Service\FileUploadService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console Command für Dateisystem-Wartung
 * Bereinigt verwaiste Dateien und führt Wartungsaufgaben durch.
 */
#[AsCommand(
    name: 'app:cleanup-files',
    description: 'Bereinigt verwaiste Dateien und führt Dateisystem-Wartung durch',
)]
class CleanupFilesCommand extends Command
{
    /**
     * CleanupFilesCommand constructor.
     *
     * @param FileUploadService $fileUploadService Service zum Bereinigen von hochgeladenen Dateien
     */
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Bereinigt verwaiste Dateien und führt Dateisystem-Wartung durch')
            ->setHelp('Dieser Command entfernt Dateien die im Dateisystem vorhanden sind aber keinen entsprechenden Datenbank-Eintrag haben.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Dateisystem-Wartung');

    try {
            $io->text('Starte Bereinigung verwaister Dateien...');

            $stats = $this->fileUploadService->cleanupOrphanedFiles();

            $io->horizontalTable(
                ['Metrik', 'Anzahl'],
                [
                    ['Gelöschte Dateien', $stats['deleted']],
                    ['Fehler', $stats['errors']],
                ]
            );

            if ($stats['errors'] > 0) {
                $io->warning("Es gab {$stats['errors']} Fehler bei der Bereinigung. Prüfen Sie die Logs für Details.");
            }

            if (0 === $stats['deleted']) {
                $io->success('Keine verwaisten Dateien gefunden. Dateisystem ist sauber.');
            } else {
                $io->success("Bereinigung abgeschlossen. {$stats['deleted']} verwaiste Dateien wurden entfernt.");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Fehler bei der Dateisystem-Wartung: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
