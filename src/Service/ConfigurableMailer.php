<?php

namespace App\Service;

use App\Factory\MailerFactory;
use App\Repository\MailSettingsRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Service-Klasse für konfigurierbaren Mailversand.
 *
 * Ermöglicht den Versand von E-Mails über verschiedene SMTP-Konfigurationen.
 */
class ConfigurableMailer
{
    public function __construct(
        private MailSettingsRepository $settingsRepository,
        private MailerFactory $mailerFactory,
    ) {
    }

    public function send(Email $email): void
    {
        $mailer = $this->getConfiguredMailer();
        $mailer->send($email);
    }

    /**
     * Ermittelt den zu verwendenden Mailer basierend auf verfügbaren Einstellungen.
     */
    private function getConfiguredMailer(): MailerInterface
    {
        // Versuche zuerst Default-Konfiguration zu finden
        $settings = $this->settingsRepository->findOneBy(['isDefault' => true]);

        // Falls keine Default-Konfiguration vorhanden, nimm die erste verfügbare
        if (!$settings) {
            $settings = $this->settingsRepository->findOneBy([]);
        }

        // Falls gar keine Konfiguration vorhanden, verwende Standard-Mailer
        if (!$settings) {
            return $this->mailerFactory->createDefault();
        }

        return $this->mailerFactory->createFromSettings($settings);
    }
}
