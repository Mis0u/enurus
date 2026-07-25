<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejette la connexion (formulaire ET reconnexion automatique via le cookie "se souvenir de moi")
 * d'un compte bloqué (`User::$isAccountBlocked`), avec un message dédié plutôt que
 * "Identifiants invalides" — cf. `templates/security/login.html.twig`
 * (`error.messageKey|trans(error.messageData, 'security')`).
 */
final class BlockedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isAccountBlocked) {
            throw new CustomUserMessageAccountStatusException('account_blocked');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
