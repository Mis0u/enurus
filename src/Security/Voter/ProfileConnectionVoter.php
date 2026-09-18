<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ProfileConnection;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, ProfileConnection>
 */
final class ProfileConnectionVoter extends Voter
{
    use ResolvesAuthenticatedUserTrait;

    /**
     * Voir le dashboard de l'autre partie : réservé aux deux parties d'une connexion acceptée,
     * dans les deux sens (la connexion est réciproque).
     */
    public const string VIEW = 'PROFILE_CONNECTION_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof ProfileConnection;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $this->resolveUser($token);

        if (null === $user) {
            return false;
        }

        return ProfileConnectionStatusEnum::ACCEPTED === $subject->status && $subject->involves($user);
    }
}
