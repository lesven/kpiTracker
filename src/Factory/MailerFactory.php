<?php

namespace App\Factory;

use App\Entity\MailSettings;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

/**
 * Factory-Klasse für die Erstellung von Mailer-Instanzen.
 * 
 * Kapselt die komplexe Logik für SMTP-Konfiguration und DSN-String-Erstellung.
 * Reduziert Code-Duplikation bei der Mailer-Erstellung.
 */
class MailerFactory
{
    public function __construct(
        private MailerInterface $defaultMailer
    ) {
    }

    /**
     * Erstellt einen Mailer basierend auf MailSettings.
     */
    public function createFromSettings(MailSettings $settings): MailerInterface
    {
        $dsn = $this->buildDsnFromSettings($settings);
        
        if ($settings->isIgnoreCertificate()) {
            $this->logCertificateWarning();
        }

        $transport = Transport::fromDsn($dsn);
        return new Mailer($transport);
    }

    /**
     * Gibt den Standard-Mailer zurück.
     */
    public function createDefault(): MailerInterface
    {
        return $this->defaultMailer;
    }

    /**
     * Erstellt einen Mailer mit expliziten SMTP-Parametern.
     */
    public function createWithParameters(
        string $host,
        int $port,
        ?string $username = null,
        ?string $password = null,
        bool $ignoreCertificate = false
    ): MailerInterface {
        $dsn = $this->buildDsn($host, $port, $username, $password);
        
        if ($ignoreCertificate) {
            $this->logCertificateWarning();
        }

        $transport = Transport::fromDsn($dsn);
        return new Mailer($transport);
    }

    /**
     * Baut DSN-String aus MailSettings Entity.
     */
    private function buildDsnFromSettings(MailSettings $settings): string
    {
        return $this->buildDsn(
            $settings->getHost(),
            $settings->getPort(),
            $settings->getUsername()?->getValue(),
            $settings->getPassword()
        );
    }

    /**
     * Baut DSN-String aus einzelnen Parametern.
     */
    private function buildDsn(string $host, int $port, ?string $username, ?string $password): string
    {
        return sprintf(
            'smtp://%s:%s@%s:%d',
            rawurlencode($username ?? ''),
            rawurlencode($password ?? ''),
            $host,
            $port
        );
    }

    /**
     * Loggt eine Warnung über Sicherheitsrisiken beim Ignorieren von Zertifikaten.
     */
    private function logCertificateWarning(): void
    {
        trigger_error(
            'Ignoring certificate validation creates a security risk. Consider using proper certificate validation.',
            E_USER_WARNING
        );
    }
}