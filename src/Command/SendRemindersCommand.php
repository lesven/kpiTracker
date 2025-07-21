<?php

namespace App\Command;

use App\Service\ReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console Command für E-Mail-Erinnerungen
 * User Stories 6, 7: Reminder für fällige KPI-Einträge und Eskalation
 */
#[AsCommand(
    name: 'app:send-reminders',
    description: 'Sendet E-Mail-Erinnerungen für fällige KPI-Einträge',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private ReminderService $reminderService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sendet E-Mail-Erinnerungen für fällige KPI-Einträge')
            ->setHelp('Dieser Command prüft alle KPIs und sendet entsprechende E-Mail-Erinnerungen an Benutzer.')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simuliert den Versand ohne tatsächlich E-Mails zu senden')
            ->addOption('test-email', 't', InputOption::VALUE_REQUIRED, 'Sendet eine Test-E-Mail an die angegebene Adresse');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Test-E-Mail senden falls angegeben
        if ($testEmail = $input->getOption('test-email')) {
            $io->title('Sende Test-E-Mail');
            
            $success = $this->reminderService->sendTestEmail($testEmail);
            
            if ($success) {
                $io->success("Test-E-Mail wurde erfolgreich an {$testEmail} gesendet.");
                return Command::SUCCESS;
            } else {
                $io->error("Fehler beim Senden der Test-E-Mail an {$testEmail}.");
                return Command::FAILURE;
            }
        }

        $isDryRun = $input->getOption('dry-run');

        $io->title('KPI-Erinnerungen verarbeiten');

        if ($isDryRun) {
            $io->note('DRY-RUN Modus: Es werden keine E-Mails versendet.');
        }

        $io->text('Prüfe fällige KPIs und versende Erinnerungen...');

        try {
            if (!$isDryRun) {
                $stats = $this->reminderService->sendDueReminders();
            } else {
                // Im Dry-Run Modus nur simulieren
                $stats = [
                    'sent' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'escalations' => 0,
                ];
                $io->note('Dry-Run: E-Mail-Versand wurde simuliert.');
            }

            // Ergebnisse anzeigen
            $io->horizontalTable(
                ['Metrik', 'Anzahl'],
                [
                    ['Erfolgreich versendet', $stats['sent']],
                    ['Fehler beim Versand', $stats['failed']],
                    ['Übersprungen', $stats['skipped']],
                    ['Eskalationen an Admins', $stats['escalations']],
                ]
            );

            if ($stats['failed'] > 0) {
                $io->warning("Es gab {$stats['failed']} Fehler beim E-Mail-Versand. Prüfen Sie die Logs für Details.");
            }

            if ($stats['sent'] === 0 && $stats['escalations'] === 0) {
                $io->info('Keine Erinnerungen zu versenden.');
            } else {
                $io->success('Erinnerungs-Verarbeitung abgeschlossen.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Fehler beim Verarbeiten der Erinnerungen: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
