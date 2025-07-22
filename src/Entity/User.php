<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Benutzer-Entity für die Anwendung
 * Implementiert Symfony Security Interface für Authentifizierung.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Diese E-Mail-Adresse wird bereits verwendet.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Konstanten für Benutzerrollen
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    // Konstanten für API-Token
    private const API_TOKEN_LENGTH = 32;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'E-Mail-Adresse ist erforderlich.')]
    #[Assert\Email(message: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.')]
    private ?string $email = null;

    /**
     * Benutzerrollen (ROLE_USER, ROLE_ADMIN).
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Gehashtes Passwort.
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 80, nullable: true, unique: true)]
    private ?string $apiToken = null;

    /**
     * KPIs die diesem Benutzer zugeordnet sind.
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: KPI::class, orphanRemoval: true)]
    private Collection $kpis;

    /**
     * Konstruktor - initialisiert Collections und setzt Erstellungsdatum.
     */
    public function __construct()
    {
        $this->kpis = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Gibt die eindeutige Benutzer-ID zurück.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt die E-Mail-Adresse des Benutzers zurück.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Setzt die E-Mail-Adresse des Benutzers.
     *
     * @param string $email Die E-Mail-Adresse
     * @return static
     */
    public function setEmail(string $email): static
    {
        $email = trim($email);
        
        // Bei leerem String - lass Symfony Validator das handhaben
        if ($email === '') {
            $this->email = $email;
            return $this;
        }
        
        // Normalisiere E-Mail (lowercase)
        $this->email = strtolower($email);

        return $this;
    }

    /**
     * Eindeutige Bezeichnung für den Benutzer (E-Mail).
     * Wird vom Symfony Security System verwendet.
     *
     * @return string Die E-Mail-Adresse als Identifier
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Gibt die Rollen des Benutzers zurück.
     * Jedem Benutzer wird automatisch ROLE_USER zugewiesen.
     *
     * @see UserInterface
     * @return array<string> Array der Benutzerrollen
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Jedem Benutzer die Grundrolle ROLE_USER geben
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    /**
     * Setzt die Rollen des Benutzers.
     *
     * @param array<string> $roles Array der Benutzerrollen
     * @return static
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Gibt das gehashte Passwort zurück.
     *
     * @see PasswordAuthenticatedUserInterface
     * @return string|null Das gehashte Passwort
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Setzt das gehashte Passwort.
     *
     * @param string $password Das gehashte Passwort
     * @return static
     */
    public function setPassword(string $password): static
    {
        // Lass leere Passwörter zu - Validierung erfolgt über Symfony Constraints
        $this->password = $password;

        return $this;
    }

    /**
     * Löscht temporäre, sensible Daten aus dem Benutzer-Objekt.
     * Wird vom Symfony Security System nach der Authentifizierung aufgerufen.
     *
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Temporäre, sensible Daten hier löschen falls vorhanden
    }

    /**
     * Gibt das Erstellungsdatum des Benutzers zurück.
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Setzt das Erstellungsdatum des Benutzers.
     *
     * @param \DateTimeImmutable $createdAt Das Erstellungsdatum
     * @return static
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gibt den Vornamen des Benutzers zurück.
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * Setzt den Vornamen des Benutzers.
     *
     * @param string|null $firstName Der Vorname
     * @return static
     */
    public function setFirstName(?string $firstName): static
    {
        if ($firstName === null) {
            $this->firstName = null;
            return $this;
        }
        
        $trimmed = trim($firstName);
        $this->firstName = $trimmed !== '' ? $trimmed : null;

        return $this;
    }

    /**
     * Gibt den Nachnamen des Benutzers zurück.
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * Setzt den Nachnamen des Benutzers.
     *
     * @param string|null $lastName Der Nachname
     * @return static
     */
    public function setLastName(?string $lastName): static
    {
        if ($lastName === null) {
            $this->lastName = null;
            return $this;
        }
        
        $trimmed = trim($lastName);
        $this->lastName = $trimmed !== '' ? $trimmed : null;

        return $this;
    }

    /**
     * Gibt den API-Token des Benutzers zurück.
     */
    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    /**
     * Setzt den API-Token des Benutzers.
     *
     * @param string|null $apiToken Der API-Token
     * @return static
     */
    public function setApiToken(?string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    /**
     * Generiert einen neuen zufälligen API-Token für den Benutzer.
     *
     * @return static
     */
    public function generateApiToken(): static
    {
        $this->apiToken = bin2hex(random_bytes(self::API_TOKEN_LENGTH));

        return $this;
    }

    /**
     * Gibt die Collection aller KPIs zurück, die diesem Benutzer zugeordnet sind.
     *
     * @return Collection<int, KPI>
     */
    public function getKpis(): Collection
    {
        return $this->kpis;
    }

    /**
     * Fügt einen KPI zu diesem Benutzer hinzu.
     *
     * @param KPI $kpi Der hinzuzufügende KPI
     * @return static
     */
    public function addKpi(KPI $kpi): static
    {
        if ($this->kpis->contains($kpi)) {
            return $this;
        }
        
        $this->kpis->add($kpi);
        $kpi->setUser($this);

        return $this;
    }

    /**
     * Entfernt einen KPI von diesem Benutzer.
     *
     * @param KPI $kpi Der zu entfernende KPI
     * @return static
     */
    public function removeKpi(KPI $kpi): static
    {
        if (!$this->kpis->removeElement($kpi)) {
            return $this;
        }
        
        // Beziehung auf null setzen wenn entfernt
        if ($kpi->getUser() === $this) {
            $kpi->setUser(null);
        }

        return $this;
    }

    /**
     * Prüft ob der Benutzer Administrator ist.
     *
     * @return bool True wenn der Benutzer ROLE_ADMIN hat
     */
    public function isAdmin(): bool
    {
        return in_array(self::ROLE_ADMIN, $this->roles, true);
    }

    /**
     * Fügt eine Rolle zum Benutzer hinzu.
     *
     * @param string $role Die hinzuzufügende Rolle
     * @return static
     */
    public function addRole(string $role): static
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    /**
     * Entfernt eine Rolle vom Benutzer.
     *
     * @param string $role Die zu entfernende Rolle
     * @return static
     */
    public function removeRole(string $role): static
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles); // Re-index array
        }

        return $this;
    }

    /**
     * Prüft ob der Benutzer eine bestimmte Rolle hat.
     *
     * @param string $role Die zu prüfende Rolle
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * Gibt den vollständigen Namen des Benutzers zurück.
     *
     * @return string Vollständiger Name oder E-Mail falls Name nicht verfügbar
     */
    public function getFullName(): string
    {
        $fullName = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
        
        return $fullName !== '' ? $fullName : $this->email ?? 'Unbekannt';
    }

    /**
     * Prüft ob der Benutzer einen API-Token hat.
     *
     * @return bool
     */
    public function hasApiToken(): bool
    {
        return $this->apiToken !== null;
    }

    /**
     * Löscht den API-Token des Benutzers.
     *
     * @return static
     */
    public function clearApiToken(): static
    {
        $this->apiToken = null;

        return $this;
    }

    /**
     * Setzt die E-Mail-Adresse des Benutzers mit Validierung.
     * Diese Methode sollte in Commands verwendet werden.
     *
     * @param string $email Die E-Mail-Adresse
     * @return static
     * @throws \InvalidArgumentException Wenn E-Mail ungültig ist
     */
    public function setEmailWithValidation(string $email): static
    {
        $email = trim($email);
        
        if ($email === '') {
            throw new \InvalidArgumentException('E-Mail-Adresse darf nicht leer sein.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }
        
        $this->email = strtolower($email);

        return $this;
    }

    /**
     * Validiert ob die E-Mail-Adresse gesetzt ist.
     *
     * @return bool
     */
    public function hasValidEmail(): bool
    {
        return $this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * String-Repräsentation des Benutzers (E-Mail-Adresse).
     *
     * @return string Die E-Mail-Adresse oder leerer String
     */
    public function __toString(): string
    {
        return $this->email ?? '';
    }
}
