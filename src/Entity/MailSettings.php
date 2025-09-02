<?php

namespace App\Entity;

use App\Domain\ValueObject\EmailAddress;
use App\Repository\MailSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailSettingsRepository::class)]
/**
 * Entity für die Verwaltung von SMTP-Mailserver-Einstellungen.
 *
 * Ermöglicht die Konfiguration mehrerer Mailserver für Reminder und Systemmails.
 */
class MailSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * Eindeutige ID der MailSettings (Auto-Increment).
     *
     * @var int|null
     */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    /**
     * Hostname oder IP des Mailservers.
     *
     * @var string
     */
    private string $host;

    #[ORM\Column(type: 'integer')]
    /**
     * Port des Mailservers (z.B. 587, 465).
     *
     * @var int
     */
    private int $port;

    #[ORM\Embedded(class: EmailAddress::class, columnPrefix: 'username_')]
    /**
     * Benutzername für die Authentifizierung (optional) - meist eine E-Mail-Adresse.
     *
     * @var EmailAddress|null
     */
    private ?EmailAddress $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    /**
     * Passwort für die Authentifizierung (optional).
     *
     * @var string|null
     */
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    /**
     * Gibt an, ob Zertifikatsfehler ignoriert werden sollen.
     *
     * @var bool
     */
    private bool $ignoreCertificate = false;

    #[ORM\Column(type: 'boolean')]
    /**
     * Gibt an, ob dies die Standard-Mailserver-Konfiguration ist.
     *
     * @var bool
     */
    private bool $isDefault = false;

    /**
     * Gibt die eindeutige ID der MailSettings zurück.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den Hostnamen des Mailservers zurück.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Setzt den Hostnamen des Mailservers.
     *
     * @param string $host
     *
     * @return self
     */
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Gibt den Port des Mailservers zurück.
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Setzt den Port des Mailservers.
     *
     * @param int $port
     *
     * @return self
     */
    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    /**
     * Gibt den Benutzernamen für die Authentifizierung zurück.
     *
     * @return EmailAddress|null
     */
    public function getUsername(): ?EmailAddress
    {
        return $this->username;
    }

    /**
     * Setzt den Benutzernamen für die Authentifizierung.
     *
     * @param EmailAddress|null $username
     *
     * @return self
     */
    public function setUsername(?EmailAddress $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Setzt den Benutzernamen aus einem String (mit Validierung).
     *
     * @param string|null $username
     *
     * @return self
     */
    public function setUsernameFromString(?string $username): self
    {
        if (null === $username || '' === trim($username)) {
            $this->username = null;
        } else {
            $this->username = new EmailAddress($username);
        }

        return $this;
    }

    /**
     * Gibt das Passwort für die Authentifizierung zurück.
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Setzt das Passwort für die Authentifizierung.
     *
     * @param string|null $password
     *
     * @return self
     */
    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Gibt an, ob Zertifikatsfehler ignoriert werden.
     *
     * @return bool
     */
    public function isIgnoreCertificate(): bool
    {
        return $this->ignoreCertificate;
    }

    /**
     * Setzt, ob Zertifikatsfehler ignoriert werden sollen.
     *
     * @param bool $ignore
     *
     * @return self
     */
    public function setIgnoreCertificate(bool $ignore): self
    {
        $this->ignoreCertificate = $ignore;

        return $this;
    }

    /**
     * Gibt an, ob dies die Standard-Mailserver-Konfiguration ist.
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Setzt, ob dies die Standard-Mailserver-Konfiguration ist.
     *
     * @param bool $isDefault
     *
     * @return self
     */
    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
