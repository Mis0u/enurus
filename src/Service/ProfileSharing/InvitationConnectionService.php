<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * À la confirmation de l'email d'un invité : connexion acceptée d'office avec son parrain, et
 * partage de profil activé (annoncé sur la page d'inscription), sans quoi le Voter, qui exige le
 * partage des deux côtés, leur cacherait l'un l'autre. Ne passe pas par
 * `ProfileConnectionRequestService` : ses garde-fous (délai après refus, limite de demandes) n'ont
 * pas de sens pour un compte qui vient d'être créé.
 */
final readonly class InvitationConnectionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private ShareCodeAssigner $shareCodeAssigner,
        private InvitationAcceptedNotifier $notifier,
    ) {
    }

    /**
     * @return User|null le parrain auquel l'invité vient d'être connecté
     */
    public function connect(User $invitee): ?User
    {
        $inviter = $invitee->invitedBy;

        if (null === $inviter || ! $inviter->isSearchable) {
            return null;
        }

        $this->turnOnProfileSharing($invitee);
        $connection = $this->createAcceptedConnection($inviter, $invitee);

        $this->entityManager->persist($connection);
        $this->entityManager->flush();
        $this->notifier->notify($connection);

        return $inviter;
    }

    private function turnOnProfileSharing(User $invitee): void
    {
        $invitee->isDiscoverable = true;

        if (null === $invitee->shareCode) {
            $this->shareCodeAssigner->assign($invitee);
        }
    }

    private function createAcceptedConnection(User $inviter, User $invitee): ProfileConnection
    {
        $now = $this->clock->now();

        $connection = new ProfileConnection();
        $connection->requester = $inviter;
        $connection->addressee = $invitee;
        $connection->status = ProfileConnectionStatusEnum::ACCEPTED;
        $connection->respondedAt = $now;
        $connection->markSeenByBothParties($now);

        return $connection;
    }
}
