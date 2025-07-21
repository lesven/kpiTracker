<?php

namespace App\Security;

use App\Entity\KPI;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Security Voter für KPI-Zugriffskontrolle
 * Stellt sicher dass Benutzer nur ihre eigenen KPIs verwalten können
 */
class KPIVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const ADD_VALUE = 'add_value';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ADD_VALUE])
            && $subject instanceof KPI;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Benutzer muss eingeloggt sein
        if (!$user instanceof User) {
            return false;
        }

        /** @var KPI $kpi */
        $kpi = $subject;

        // Administratoren haben immer Zugriff
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Prüfen ob die KPI dem aktuellen Benutzer gehört
        return $this->canAccess($kpi, $user, $attribute);
    }

    private function canAccess(KPI $kpi, User $user, string $attribute): bool
    {
        // Benutzer kann nur seine eigenen KPIs verwalten
        if ($kpi->getUser() !== $user) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT => true,
            self::DELETE => true,
            self::ADD_VALUE => true,
            default => false,
        };
    }
}
