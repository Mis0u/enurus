<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Links the current PHP session row to the authenticated user, so
 * App\Service\Security\SessionInvalidationService can later find and purge it. Fires on both
 * classic form login and remember-me cookie re-authentication (both dispatch LoginSuccessEvent).
 */
final readonly class SessionLinkListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        if (null === $user->id) {
            return;
        }

        $sessionId = $event->getRequest()->getSession()->getId();

        $this->connection->executeStatement(
            'UPDATE sessions SET user_id = :userId WHERE session_id = :sessionId',
            [
                'userId' => $user->id->toRfc4122(),
                'sessionId' => $sessionId,
            ],
        );
    }
}
