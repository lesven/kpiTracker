<?php

namespace App\Entity;

use App\Repository\MailSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailSettingsRepository::class)]
class MailSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $host;

    #[ORM\Column(type: 'integer')]
    private int $port;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    private bool $ignoreCertificate = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function isIgnoreCertificate(): bool
    {
        return $this->ignoreCertificate;
    }

    public function setIgnoreCertificate(bool $ignore): self
    {
        $this->ignoreCertificate = $ignore;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
