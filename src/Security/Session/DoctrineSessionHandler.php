<?php

declare(strict_types=1);

namespace App\Security\Session;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use function Symfony\Component\Clock\now;
use Symfony\Component\Uid\Uuid;

/**
 * Session storage backed by the `sessions` table (see App\Entity\Session), read/written through
 * raw DBAL rather than the ORM: session I/O fires on every request and must never trigger
 * EntityManager::flush(), which would flush unrelated pending changes elsewhere in the request.
 * Same reasoning as Symfony's own PdoSessionHandler.
 *
 * user_id is intentionally left untouched here — it is populated by
 * App\EventListener\SessionLinkListener once a session is actually authenticated.
 */
final class DoctrineSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private const string NOT_EXPIRED = 'updated_at + (lifetime * INTERVAL \'1 second\') > :now';

    private const string EXPIRED = 'updated_at + (lifetime * INTERVAL \'1 second\') < :now';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $data = $this->connection->fetchOne(
            'SELECT data FROM sessions WHERE session_id = :id AND ' . self::NOT_EXPIRED,
            [
                'id' => $id,
                'now' => $this->now(),
            ],
        );

        if (false === $data) {
            return '';
        }

        return $this->toBinaryString($data);
    }

    public function write(string $id, string $data): bool
    {
        $now = $this->now();
        $lifetime = $this->maxLifetime();

        $this->connection->executeStatement(
            'INSERT INTO sessions (id, session_id, data, lifetime, updated_at)
             VALUES (:sessionRowId, :id, :data, :lifetime, :now)
             ON CONFLICT (session_id) DO UPDATE
                SET data = EXCLUDED.data, lifetime = EXCLUDED.lifetime, updated_at = EXCLUDED.updated_at',
            [
                'sessionRowId' => Uuid::v7()->toRfc4122(),
                'id' => $id,
                'data' => $data,
                'lifetime' => $lifetime,
                'now' => $now,
            ],
            [
                'sessionRowId' => ParameterType::STRING,
                'id' => ParameterType::STRING,
                'data' => ParameterType::LARGE_OBJECT,
                'lifetime' => ParameterType::INTEGER,
                'now' => ParameterType::STRING,
            ],
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->connection->executeStatement('DELETE FROM sessions WHERE session_id = :id', [
            'id' => $id,
        ]);

        return true;
    }

    public function gc(int $max_lifetime): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM sessions WHERE ' . self::EXPIRED,
            [
                'now' => $this->now(),
            ],
        );
    }

    public function validateId(string $id): bool
    {
        return '' !== $this->read($id);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->connection->executeStatement(
            'UPDATE sessions SET updated_at = :now WHERE session_id = :id',
            [
                'now' => $this->now(),
                'id' => $id,
            ],
        );

        return true;
    }

    private function maxLifetime(): int
    {
        $iniValue = (int) \ini_get('session.gc_maxlifetime');

        return 0 < $iniValue ? $iniValue : 1440;
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function toBinaryString(mixed $data): string
    {
        if (\is_resource($data)) {
            $contents = \stream_get_contents($data);

            return false !== $contents ? $contents : '';
        }

        return \is_string($data) ? $data : '';
    }
}
