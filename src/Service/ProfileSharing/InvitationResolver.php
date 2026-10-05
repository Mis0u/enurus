<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Retrouve le parrain derrière le `?invitation=<shareCode>` d'un lien d'invitation. Un code
 * invalide, inconnu ou celui d'un profil qui ne se partage plus ne donne personne : l'inscription
 * continue alors normalement, sans message d'erreur.
 */
final readonly class InvitationResolver
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function resolveInviter(mixed $code): ?User
    {
        if (! \is_string($code) || User::SHARE_CODE_LENGTH !== mb_strlen($code)) {
            return null;
        }

        $inviter = $this->userRepository->findOneByShareCode(mb_strtoupper($code));

        return true === $inviter?->isSearchable ? $inviter : null;
    }
}
