<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
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
     * Voir la liste et le détail des séances de l'autre partie : en plus de tout ce qu'exige
     * `VIEW`, le propriétaire des séances doit avoir explicitement activé `shareWorkouts` — opt-in
     * secondaire, jamais requis dans l'autre sens (voir ses propres séances n'est pas une
     * condition pour consulter celles de l'autre, ce n'est pas réciproque).
     */
    public const string VIEW_WORKOUTS = 'PROFILE_CONNECTION_VIEW_WORKOUTS';

    /**
     * Voir tous les badges de l'autre partie : mêmes conditions que `VIEW`, sans opt-in dédié
     * (décision actée, rien de sensible). Refusé si le propriétaire a masqué son widget Badges,
     * sur son propre dashboard ou pour ses seules connexions — même règle que les widgets du
     * dashboard partagé (`DashboardViewDataBuilder::resolveVisibleWidgets()`).
     */
    public const string VIEW_BADGES = 'PROFILE_CONNECTION_VIEW_BADGES';

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
        self::VIEW_WORKOUTS,
        self::VIEW_BADGES,
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
            self::VIEW => $this->canView($subject, $user),
            self::VIEW_WORKOUTS => $this->canView($subject, $user) && $subject->counterpartOf($user)->shareWorkouts,
            self::VIEW_BADGES => $this->canView($subject, $user) && $this->sharesBadges($subject->counterpartOf($user)),
            self::RESPOND => $subject->addressee === $user,
            self::CANCEL => $subject->requester === $user,
            self::REVOKE => $subject->involves($user),
            default => false,
        };
    }

    private function canView(ProfileConnection $connection, User $user): bool
    {
        return ProfileConnectionStatusEnum::ACCEPTED === $connection->status
            && $connection->involves($user)
            && $user->isDiscoverable
            && $connection->counterpartOf($user)->isDiscoverable;
    }

    private function sharesBadges(User $owner): bool
    {
        $badges = DashboardWidgetEnum::BADGES->value;

        return ! \in_array($badges, $owner->hiddenWidgets, true)
            && ! \in_array($badges, $owner->hiddenSharedWidgets, true);
    }
}
