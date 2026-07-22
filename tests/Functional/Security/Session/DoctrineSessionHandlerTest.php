<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Session;

use App\Security\Session\DoctrineSessionHandler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineSessionHandlerTest extends KernelTestCase
{
    private DoctrineSessionHandler $handler;

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->handler = new DoctrineSessionHandler($connection);
    }

    public function testWriteThenReadReturnsStoredData(): void
    {
        $sessionId = $this->uniqueSessionId();

        self::assertTrue($this->handler->write($sessionId, 'foo=bar'));
        self::assertSame('foo=bar', $this->handler->read($sessionId));
    }

    public function testWriteTwiceUpdatesDataWithoutDuplicatingRow(): void
    {
        $sessionId = $this->uniqueSessionId();

        $this->handler->write($sessionId, 'first');
        $this->handler->write($sessionId, 'second');

        self::assertSame('second', $this->handler->read($sessionId));

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions WHERE session_id = :id', [
            'id' => $sessionId,
        ]);

        if (! is_numeric($count)) {
            throw new \LogicException('Expected COUNT(*) to return a numeric value.');
        }

        self::assertSame(1, (int) $count);
    }

    public function testReadUnknownSessionReturnsEmptyString(): void
    {
        self::assertSame('', $this->handler->read($this->uniqueSessionId()));
    }

    public function testDestroyRemovesSession(): void
    {
        $sessionId = $this->uniqueSessionId();
        $this->handler->write($sessionId, 'foo=bar');

        self::assertTrue($this->handler->destroy($sessionId));
        self::assertSame('', $this->handler->read($sessionId));
    }

    public function testValidateIdReflectsExistence(): void
    {
        $sessionId = $this->uniqueSessionId();

        self::assertFalse($this->handler->validateId($sessionId));

        $this->handler->write($sessionId, 'foo=bar');

        self::assertTrue($this->handler->validateId($sessionId));
    }

    public function testExpiredSessionIsNotReadable(): void
    {
        $sessionId = $this->uniqueSessionId();
        $this->insertExpiredRow($sessionId);

        self::assertSame('', $this->handler->read($sessionId));
    }

    public function testGcRemovesExpiredSessionsOnly(): void
    {
        $expiredId = $this->uniqueSessionId();
        $activeId = $this->uniqueSessionId();

        $this->insertExpiredRow($expiredId);
        $this->handler->write($activeId, 'foo=bar');

        $this->handler->gc(1440);

        self::assertSame('', $this->handler->read($expiredId));
        self::assertSame('foo=bar', $this->handler->read($activeId));
    }

    private function insertExpiredRow(string $sessionId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO sessions (id, session_id, data, lifetime, updated_at) VALUES (:id, :sessionId, :data, :lifetime, :updatedAt)',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'sessionId' => $sessionId,
                'data' => 'expired',
                'lifetime' => 1,
                'updatedAt' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function uniqueSessionId(): string
    {
        return 'test-session-' . bin2hex(random_bytes(16));
    }
}
