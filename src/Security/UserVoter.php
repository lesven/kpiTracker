<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Security-Voter für die Zugriffskontrolle auf Benutzer-Entities.
 *
 * Stellt sicher, dass nur Administratoren andere Benutzer verwalten können und Benutzer ihr eigenes Profil bearbeiten dürfen.
 */
class UserVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        // Benutzer muss eingeloggt sein
        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($targetUser, $currentUser),
            self::EDIT => $this->canEdit($targetUser, $currentUser),
            self::DELETE => $this->canDelete($targetUser, $currentUser),
            default => false,
        };
    }

    private function canView(User $targetUser, User $currentUser): bool
    {
        // Benutzer kann sich selbst anzeigen
        if ($targetUser === $currentUser) {
            return true;
        }

        // Administratoren können alle Benutzer anzeigen
        return in_array(User::ROLE_ADMIN, $currentUser->getRoles(), true);
    }

    private function canEdit(User $targetUser, User $currentUser): bool
    {
        // Benutzer kann sich selbst bearbeiten (z.B. Profil)
        if ($targetUser === $currentUser) {
            return true;
        }

        // Nur Administratoren können andere Benutzer bearbeiten
        return in_array(User::ROLE_ADMIN, $currentUser->getRoles(), true);
    }

    private function canDelete(User $targetUser, User $currentUser): bool
    {
        // Benutzer kann sich nicht selbst löschen
        if ($targetUser === $currentUser) {
            return false;
        }

        // Nur Administratoren können Benutzer löschen
        return in_array(User::ROLE_ADMIN, $currentUser->getRoles(), true);
    }
}
