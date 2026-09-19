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
     * dans les deux sens (la connexion est réciproque). Les deux parties doivent en plus partager
     * actuellement leur profil (`isDiscoverable`) — si l'une des deux désactive son partage, le
     * voter refuse dans les deux sens (elle ne voit plus personne, personne ne la voit plus),
     * même si la connexion elle-même reste acceptée en base.
     */
    public const string VIEW = 'PROFILE_CONNECTION_VIEW';

    /**
     * Accepter ou refuser : réservé au destinataire. L'état de la demande (encore en attente ?)
     * n'est pas vérifié ici mais par `ProfileConnectionResponseService`.
     */
    public const string RESPOND = 'PROFILE_CONNECTION_RESPOND';

    /**
     * Retirer sa propre demande : réservé au demandeur.
     */
    public const string CANCEL = 'PROFILE_CONNECTION_CANCEL';

    /**
     * Mettre fin à une connexion : chacune des deux parties peut le faire.
     */
    public const string REVOKE = 'PROFILE_CONNECTION_REVOKE';

    private const array SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::RESPOND,
        self::CANCEL,
        self::REVOKE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::SUPPORTED_ATTRIBUTES, true) && $subject instanceof ProfileConnection;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $this->resolveUser($token);

        if (null === $user) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => ProfileConnectionStatusEnum::ACCEPTED === $subject->status
                && $subject->involves($user)
                && $user->isDiscoverable
                && $subject->counterpartOf($user)->isDiscoverable,
            self::RESPOND => $subject->addressee === $user,
            self::CANCEL => $subject->requester === $user,
            self::REVOKE => $subject->involves($user),
            default => false,
        };
    }
}
