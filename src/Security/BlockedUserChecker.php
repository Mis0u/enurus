<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejette la connexion (formulaire ET reconnexion automatique via le cookie "se souvenir de moi")
 * d'un compte bloqué (`User::$isAccountBlocked`) ou non encore vérifié par email
 * (`User::$isVerified`), avec un message dédié plutôt que "Identifiants invalides" — cf.
 * `templates/security/login.html.twig` (`error.messageKey|trans(error.messageData, 'security')`).
 * Un seul `user_checker` par firewall (cf. `security.yaml`) : les deux gardes vivent ici plutôt
 * que dans deux classes séparées.
 */
final class BlockedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        if ($user->isAccountBlocked) {
            throw new CustomUserMessageAccountStatusException('account_blocked');
        }

        if (! $user->isVerified) {
            throw new CustomUserMessageAccountStatusException('account_not_verified');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
