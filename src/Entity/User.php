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
 * Implementiert Symfony Security Interface für Authentifizierung
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Diese E-Mail-Adresse wird bereits verwendet.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'E-Mail-Adresse ist erforderlich.')]
    #[Assert\Email(message: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.')]
    private ?string $email = null;

    /**
     * Benutzerrollen (ROLE_USER, ROLE_ADMIN)
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Gehashtes Passwort
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * KPIs die diesem Benutzer zugeordnet sind
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: KPI::class, orphanRemoval: true)]
    private Collection $kpis;

    public function __construct()
    {
        $this->kpis = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Eindeutige Bezeichnung für den Benutzer (E-Mail)
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Jedem Benutzer die Grundrolle ROLE_USER geben
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Temporäre, sensible Daten hier löschen falls vorhanden
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, KPI>
     */
    public function getKpis(): Collection
    {
        return $this->kpis;
    }

    public function addKpi(KPI $kpi): static
    {
        if (!$this->kpis->contains($kpi)) {
            $this->kpis->add($kpi);
            $kpi->setUser($this);
        }

        return $this;
    }

    public function removeKpi(KPI $kpi): static
    {
        if ($this->kpis->removeElement($kpi)) {
            // Beziehung auf null setzen wenn entfernt
            if ($kpi->getUser() === $this) {
                $kpi->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Prüft ob der Benutzer Administrator ist
     */
    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles);
    }

    public function __toString(): string
    {
        return $this->email ?? '';
    }
}
