<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Console Command für Admin-Benutzer-Erstellung
 * User Story 2: Administrator kann Benutzer anlegen.
 */
#[AsCommand(
    name: 'app:create-admin',
    description: 'Erstellt einen neuen Administrator-Benutzer',
)]
class CreateAdminCommand extends Command
{
    /**
     * CreateAdminCommand constructor.
     *
     * @param EntityManagerInterface      $entityManager  Doctrine EntityManager zum Persistieren von Benutzern
     * @param UserPasswordHasherInterface $passwordHasher Passwort-Hasher für die sichere Speicherung
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    /**
     * Konfiguriert den Symfony Console Command.
     *
     * Fügt Argumente und Optionen hinzu, die beim Aufruf des Commands verwendet werden können.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Erstellt einen neuen Administrator-Benutzer')
            ->setHelp('Dieser Command erstellt einen neuen Benutzer mit Administrator-Rechten.')
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail-Adresse des Administrators')
            ->addArgument('password', InputArgument::OPTIONAL, 'Passwort (wird generiert falls nicht angegeben)')
            ->addOption('first-name', 'f', InputOption::VALUE_REQUIRED, 'Vorname des Administrators')
            ->addOption('last-name', 'l', InputOption::VALUE_REQUIRED, 'Nachname des Administrators')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Überschreibt existierenden Benutzer');
    }

    /**
     * Führt die Erstellung oder Aktualisierung eines Administrator-Benutzers aus.
     *
     * Erwartet als Argument die E-Mail und optional ein Passwort. Überprüft E-Mail-Format,
     * hasht das Passwort und persistiert den Benutzer in der Datenbank.
     *
     * Gibt Command::SUCCESS bei Erfolg und Command::FAILURE bei Fehlern zurück.
     *
     * @param InputInterface  $input  Konsolen-Eingabe
     * @param OutputInterface $output Konsolen-Ausgabe
     *
     * @return int Exit-Code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getOption('first-name') ?? 'Admin';
        $lastName = $input->getOption('last-name') ?? 'User';
        $force = $input->getOption('force');

        // E-Mail validieren
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Ungültige E-Mail-Adresse.');

            return Command::FAILURE;
        }

        // Prüfen ob Benutzer bereits existiert
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser && !$force) {
            $io->error("Benutzer mit E-Mail '{$email}' existiert bereits. Verwenden Sie --force zum Überschreiben.");

            return Command::FAILURE;
        }

        // Passwort generieren falls nicht angegeben
        if (!$password) {
            $password = $this->generatePassword();
            $io->note("Automatisch generiertes Passwort: {$password}");
        }

        // Passwort validieren
        if (mb_strlen($password) < 8) {
            $io->error('Das Passwort muss mindestens 8 Zeichen lang sein.');

            return Command::FAILURE;
        }

        try {
            $this->entityManager->beginTransaction();

            if ($existingUser) {
                $user = $existingUser;
                $io->note("Aktualisiere existierenden Benutzer '{$email}'...");
            } else {
                $user = new User();
                $io->note("Erstelle neuen Administrator '{$email}'...");
            }

            $user->setEmailWithValidation($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setRoles([User::ROLE_ADMIN, User::ROLE_USER]);

            // Passwort hashen
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            if (!$existingUser) {
                $this->entityManager->persist($user);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $io->success('Administrator wurde erfolgreich erstellt/aktualisiert:');
            $io->horizontalTable(
                ['Eigenschaft', 'Wert'],
                [
                    ['E-Mail', $user->getEmail()],
                    ['Vorname', $user->getFirstName()],
                    ['Nachname', $user->getLastName()],
                    ['Rollen', implode(', ', $user->getRoles())],
                    ['Passwort', str_repeat('*', mb_strlen($password))],
                ]
            );

            $io->note('Notieren Sie sich das Passwort für die erste Anmeldung.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $io->error('Fehler beim Erstellen des Administrators: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Generiert ein sicheres zufälliges Passwort.
     */
    private function generatePassword(int $length = 12): string
    {
        /**
         * Generiert ein sicheres, zufälliges Passwort für Administratoren.
         *
         * Das Passwort enthält Zahlen, Buchstaben und Sonderzeichen und erfüllt die Mindestlänge.
         *
         * @param int $length Die gewünschte Passwortlänge (Standard: 12)
         *
         * @throws \Exception Falls random_int fehlschlägt
         *
         * @return string Das generierte Passwort
         */
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; ++$i) {
            $password .= $characters[random_int(0, mb_strlen($characters) - 1)];
        }

        return $password;
    }
}
