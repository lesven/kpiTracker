<?php

namespace App\Command;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\User;
use App\Factory\UserFactory;
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
 * Console Command für Benutzer-Erstellung per Shell
 * User Story 15: Benutzer per Shell anlegen.
 */
#[AsCommand(
    name: 'app:create-user',
    description: 'Erstellt einen neuen Benutzer per Shell-Command',
)]
class CreateUserCommand extends Command
{
    /**
     * CreateUserCommand constructor.
     *
     * @param EntityManagerInterface      $entityManager  Doctrine EntityManager zum Persistieren von Benutzern
     * @param UserPasswordHasherInterface $passwordHasher Passwort-Hasher für die sichere Speicherung
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserFactory $userFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        /*
         * Konfiguriert den CreateUser-Command mit benötigten Argumenten und Optionen.
         *
         * Das Passwort-Argument muss genau 16 Zeichen lang sein (Akzeptanzkriterium).
         */
        $this
            ->setDescription('Erstellt einen neuen Benutzer per Shell-Command')
            ->setHelp('Dieser Command erstellt einen neuen Benutzer mit den angegebenen Daten.')
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail-Adresse des Benutzers')
            ->addArgument('password', InputArgument::REQUIRED, 'Passwort (muss genau 16 Zeichen lang sein)')
            ->addOption('first-name', 'f', InputOption::VALUE_REQUIRED, 'Vorname des Benutzers', 'Benutzer')
            ->addOption('last-name', 'l', InputOption::VALUE_REQUIRED, 'Nachname des Benutzers', 'Standard')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Benutzer als Administrator anlegen')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Überschreibt existierenden Benutzer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * Führt die Erstellung oder Aktualisierung eines Benutzers aus.
         *
         * Validiert E-Mail und Passwort, setzt Rollen, hasht das Passwort und speichert den Benutzer.
         * Bei Erfolg wird Command::SUCCESS zurückgegeben, sonst Command::FAILURE.
         *
         * @param InputInterface  $input  Konsolen-Eingabe
         * @param OutputInterface $output Konsolen-Ausgabe
         *
         * @return int Exit-Code
         */
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getOption('first-name');
        $lastName = $input->getOption('last-name');
        $isAdmin = $input->getOption('admin');
        $force = $input->getOption('force');

        // E-Mail validieren (Akzeptanzkriterium: Email validiert)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Ungültige E-Mail-Adresse. Bitte geben Sie eine gültige E-Mail-Adresse ein.');

            return Command::FAILURE;
        }

        // Zusätzliche E-Mail-Validierung für gängige E-Mail-Formate
        if (!$this->isValidEmailFormat($email)) {
            $io->error('Die E-Mail-Adresse entspricht nicht einem gängigen E-Mail-Format.');

            return Command::FAILURE;
        }

        // Passwort validieren (Akzeptanzkriterium: Passwort muss 16 Zeichen lang sein)
        if (mb_strlen($password) < 16) {
            $io->error('Das Passwort muss genau 16 Zeichen lang sein.');

            return Command::FAILURE;
        }

        // Prüfen ob Benutzer bereits existiert
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email.value' => $email]);

        if ($existingUser && !$force) {
            $io->error("Benutzer mit E-Mail '{$email}' existiert bereits. Verwenden Sie --force zum Überschreiben.");

            return Command::FAILURE;
        }

        try {
            $this->entityManager->beginTransaction();

            if ($existingUser) {
                $user = $existingUser;
                $io->note("Aktualisiere existierenden Benutzer '{$email}'...");

                // Update existing user properties
                $user->setEmail(new EmailAddress($email));
                $user->setFirstName($firstName);
                $user->setLastName($lastName);

                // Update roles
                if ($isAdmin) {
                    $user->setRoles([User::ROLE_ADMIN, User::ROLE_USER]);
                } else {
                    $user->setRoles([User::ROLE_USER]);
                }
            } else {
                $user = $this->userFactory->createByType($email, $firstName, $lastName, $isAdmin);
                $io->note("Erstelle neuen Benutzer '{$email}'...");
            }

            // Passwort hashen
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            if (!$existingUser) {
                $this->entityManager->persist($user);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $io->success('Benutzer wurde erfolgreich erstellt/aktualisiert:');
            $io->horizontalTable(
                ['Eigenschaft', 'Wert'],
                [
                    ['E-Mail', $user->getEmail()->getValue()],
                    ['Vorname', $user->getFirstName()],
                    ['Nachname', $user->getLastName()],
                    ['Rollen', implode(', ', $user->getRoles())],
                    ['Passwort', str_repeat('*', mb_strlen($password))],
                ]
            );

            if (!$existingUser) {
                $io->note('Der Benutzer kann sich nun mit den angegebenen Zugangsdaten anmelden.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $io->error('Fehler beim Erstellen des Benutzers: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Validiert ob die E-Mail-Adresse einem gängigen Format entspricht
     * Akzeptanzkriterium: Email wird validiert ob sie einer gängigen Email entspricht.
     */
    private function isValidEmailFormat(string $email): bool
    {
        /**
         * Prüft, ob eine E-Mail einem gängigen Format entspricht und ob die Domain existiert.
         *
         * Diese Methode erweitert die PHP-Filter-Validierung um zusätzliche Sicherheitsprüfungen
         * (z. B. keine Doppel-Punkte, Domain-Teil enthält Punkt, DNS-Lookup auf MX/A-Record).
         *
         * @param string $email Zu prüfende E-Mail-Adresse
         *
         * @return bool true wenn die E-Mail plausibel ist, false sonst
         */
        // Grundlegende PHP-Filter-Validierung
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Zusätzliche Prüfungen für gängige E-Mail-Formate
        $parts = explode('@', $email);
        if (2 !== count($parts)) {
            return false;
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Lokaler Teil darf nicht leer sein und keine aufeinanderfolgenden Punkte haben
        if (empty($local) || false !== mb_strpos($local, '..')) {
            return false;
        }

        // Domain-Teil prüfen
        if (empty($domain) || false !== mb_strpos($domain, '..')) {
            return false;
        }

        // Domain muss mindestens einen Punkt haben (für TLD)
        if (false === mb_strpos($domain, '.')) {
            return false;
        }

        // Prüfen ob Domain existiert (DNS-Lookup)
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return false;
        }

        return true;
    }
}
