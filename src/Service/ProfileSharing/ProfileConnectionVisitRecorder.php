<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Visite par `$viewer` d'une page de l'autre partie (dashboard, liste ou détail de ses séances) :
 * ses séances déjà créées ne sont plus signalées comme nouvelles.
 */
final readonly class ProfileConnectionVisitRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function record(ProfileConnection $connection, User $viewer): void
    {
        $connection->markSeenBy($viewer, $this->clock->now());

        $this->entityManager->flush();
    }
}
