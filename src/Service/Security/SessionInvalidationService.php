<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Repository\SessionRepository;

readonly class SessionInvalidationService
{
    public function __construct(
        private SessionRepository $sessionRepository,
    ) {
    }

    public function invalidateAllSessions(User $user): void
    {
        if (null === $user->id) {
            return;
        }

        $this->sessionRepository->deleteAllForUser($user->id);
    }

    public function invalidateOtherSessions(User $user, string $currentSessionId): void
    {
        if (null === $user->id) {
            return;
        }

        $this->sessionRepository->deleteOtherSessionsForUser($user->id, $currentSessionId);
    }
}
