<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use function Symfony\Component\Clock\now;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Uid\Uuid;

/**
 * Links the current PHP session row to the authenticated user, so
 * App\Service\Security\SessionInvalidationService can later find and purge it. Fires on both
 * classic form login and remember-me cookie re-authentication (both dispatch LoginSuccessEvent).
 *
 * Runs mid-request, well before App\Security\Session\DoctrineSessionHandler::write() persists the
 * session row (PHP only flushes session storage at session_write_close(), i.e. request shutdown).
 * On a fresh login the row doesn't exist yet, so a plain UPDATE silently matches 0 rows and
 * user_id never gets linked — the session then becomes unreachable to
 * SessionInvalidationService::invalidateAllSessions() for its whole lifetime. Upsert instead,
 * touching only user_id on conflict so a later write() (which upserts data/lifetime/updated_at,
 * never user_id) can't clobber it.
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
            'INSERT INTO sessions (id, session_id, data, lifetime, updated_at, user_id)
             VALUES (:id, :sessionId, :data, :lifetime, :now, :userId)
             ON CONFLICT (session_id) DO UPDATE
                SET user_id = EXCLUDED.user_id',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'sessionId' => $sessionId,
                'data' => '',
                'lifetime' => $this->maxLifetime(),
                'now' => now()->format('Y-m-d H:i:s'),
                'userId' => $user->id->toRfc4122(),
            ],
            [
                'id' => ParameterType::STRING,
                'sessionId' => ParameterType::STRING,
                'data' => ParameterType::LARGE_OBJECT,
                'lifetime' => ParameterType::INTEGER,
                'now' => ParameterType::STRING,
                'userId' => ParameterType::STRING,
            ],
        );
    }

    private function maxLifetime(): int
    {
        $iniValue = (int) \ini_get('session.gc_maxlifetime');

        return 0 < $iniValue ? $iniValue : 1440;
    }
}
