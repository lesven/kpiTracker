<?php

namespace App\Service;

use App\Repository\MailSettingsRepository;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class ConfigurableMailer
{
    public function __construct(
        private MailSettingsRepository $settingsRepository,
        private MailerInterface $defaultMailer,
    ) {
    }

    public function send(Email $email): void
    {
        $settings = $this->settingsRepository->findOneBy(['isDefault' => true]);
        if (!$settings) {
            $this->defaultMailer->send($email);

            return;
        }

        $dsn = sprintf(
            'smtp://%s:%s@%s:%d',
            rawurlencode($settings->getUsername() ?? ''),
            rawurlencode($settings->getPassword() ?? ''),
            $settings->getHost(),
            $settings->getPort()
        );

        if ($settings->isIgnoreCertificate()) {
            // Log a warning about the security implications of ignoring certificates
            trigger_error(
                'Ignoring certificate validation creates a security risk. Consider using proper certificate validation.',
                E_USER_WARNING
            );
        }

        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        $mailer->send($email);
    }
}
