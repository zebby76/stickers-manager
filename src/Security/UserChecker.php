<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks authentication for accounts that are not yet approved by an admin or
 * that have been disabled (banned). Runs for every firewall authenticator
 * (form login and Google SSO).
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été désactivé. Contactez un administrateur.'
            );
        }

        if (!$user->isApproved()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est en attente de validation par un administrateur.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Nothing to check after authentication.
    }
}
