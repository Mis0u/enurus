<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Ne porte que les transitions d'état. Savoir QUI a le droit de répondre (le destinataire) ou de
 * révoquer (l'une des deux parties) relève du Voter, pas d'ici.
 */
final readonly class ProfileConnectionResponseService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function accept(ProfileConnection $connection): void
    {
        $this->transition($connection, ProfileConnectionStatusEnum::PENDING, ProfileConnectionStatusEnum::ACCEPTED);
        $connection->markSeenByBothParties($connection->respondedAt ?? throw new \LogicException('An answered connection has a response date.'));

        $this->entityManager->flush();
    }

    public function decline(ProfileConnection $connection): void
    {
        $this->transition($connection, ProfileConnectionStatusEnum::PENDING, ProfileConnectionStatusEnum::DECLINED);

        $this->entityManager->flush();
    }

    public function revoke(ProfileConnection $connection): void
    {
        $this->transition($connection, ProfileConnectionStatusEnum::ACCEPTED, ProfileConnectionStatusEnum::REVOKED);

        $this->entityManager->flush();
    }

    /**
     * Retrait par le demandeur d'une demande encore sans réponse : la ligne est supprimée, pas
     * marquée — il n'y a rien à conserver, contrairement à un refus qui déclenche le délai de
     * nouvelle demande.
     */
    public function cancel(ProfileConnection $connection): void
    {
        $this->assertStatus($connection, ProfileConnectionStatusEnum::PENDING);

        $this->entityManager->remove($connection);
        $this->entityManager->flush();
    }

    private function transition(
        ProfileConnection $connection,
        ProfileConnectionStatusEnum $expected,
        ProfileConnectionStatusEnum $target,
    ): void {
        $this->assertStatus($connection, $expected);

        $connection->status = $target;
        $connection->respondedAt = $this->clock->now();
    }

    private function assertStatus(ProfileConnection $connection, ProfileConnectionStatusEnum $expected): void
    {
        if ($expected !== $connection->status) {
            throw new ProfileConnectionException(ProfileConnectionFailureReasonEnum::INVALID_TRANSITION);
        }
    }
}
