<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\User;
use App\Repository\ProfileConnectionRepository;
use Twig\Attribute\AsTwigFunction;

final readonly class ProfileConnectionPendingCountExtension
{
    public function __construct(
        private ProfileConnectionRepository $profileConnectionRepository,
    ) {
    }

    #[AsTwigFunction('profile_connection_pending_count')]
    public function pendingCount(User $user): int
    {
        return $this->profileConnectionRepository->countPendingReceivedBy($user);
    }
}
