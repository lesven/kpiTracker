<?php

namespace App\Factory;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\User;

/**
 * Factory-Klasse für die Erstellung von User-Entitäten.
 *
 * Kapselt die Logik für die Erstellung verschiedener User-Typen und
 * reduziert Code-Duplikation bei der User-Erstellung.
 */
class UserFactory
{
    /**
     * Erstellt einen neuen Standard-Benutzer mit ROLE_USER.
     */
    public function createRegularUser(string $email, string $firstName, string $lastName): User
    {
        $user = new User();
        $user->setEmail(new EmailAddress($email));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([User::ROLE_USER]);

        return $user;
    }

    /**
     * Erstellt einen neuen Administrator mit ROLE_ADMIN und ROLE_USER.
     */
    public function createAdmin(string $email, string $firstName, string $lastName): User
    {
        $user = new User();
        $user->setEmail(new EmailAddress($email));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([User::ROLE_ADMIN, User::ROLE_USER]);

        return $user;
    }

    /**
     * Erstellt einen neuen Benutzer mit konfigurierbaren Rollen.
     */
    public function createWithRoles(string $email, string $firstName, string $lastName, array $roles): User
    {
        $user = new User();
        $user->setEmail(new EmailAddress($email));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);

        return $user;
    }

    /**
     * Erstellt einen neuen Benutzer basierend auf Admin-Flag.
     */
    public function createByType(string $email, string $firstName, string $lastName, bool $isAdmin): User
    {
        return $isAdmin
            ? $this->createAdmin($email, $firstName, $lastName)
            : $this->createRegularUser($email, $firstName, $lastName);
    }
}
