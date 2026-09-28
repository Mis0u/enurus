<?php

declare(strict_types=1);

namespace App\Service\Onboarding;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GuidedTourService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Vrai uniquement au tout premier affichage du dashboard : le tour est marqué vu à ce moment-là,
     * pas à sa fin — fermé, ignoré ou interrompu, il ne se rejoue jamais tout seul.
     */
    public function consumeFirstDisplay(User $user): bool
    {
        if ($user->guidedTourSeen) {
            return false;
        }

        $user->guidedTourSeen = true;
        $this->entityManager->flush();

        return true;
    }
}
