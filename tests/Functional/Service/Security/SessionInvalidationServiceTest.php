<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Security;

use App\Entity\User;
use App\Service\Security\SessionInvalidationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class SessionInvalidationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private Connection $connection;

    private SessionInvalidationService $sessionInvalidationService;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $this->connection = $connection;

        /** @var SessionInvalidationService $service */
        $service = static::getContainer()->get(SessionInvalidationService::class);
        $this->sessionInvalidationService = $service;
    }

    public function testInvalidateOtherSessionsKeepsOnlyCurrentSession(): void
    {
        $user = $this->createUser('session-invalidation-other@test.com');
        $current = $this->createSessionRow($user, 'current-session');
        $this->createSessionRow($user, 'other-session-1');
        $this->createSessionRow($user, 'other-session-2');

        $this->sessionInvalidationService->invalidateOtherSessions($user, $current);

        $remaining = $this->findSessionIdsForUser($user);
        self::assertSame(['current-session'], $remaining);

        $this->cleanupUser($user);
    }

    public function testInvalidateAllSessionsRemovesEveryOne(): void
    {
        $user = $this->createUser('session-invalidation-all@test.com');
        $this->createSessionRow($user, 'session-1');
        $this->createSessionRow($user, 'session-2');

        $this->sessionInvalidationService->invalidateAllSessions($user);

        self::assertSame([], $this->findSessionIdsForUser($user));

        $this->cleanupUser($user);
    }

    public function testInvalidationDoesNotAffectOtherUsersSessions(): void
    {
        $userA = $this->createUser('session-invalidation-a@test.com');
        $userB = $this->createUser('session-invalidation-b@test.com');

        $this->createSessionRow($userA, 'user-a-session');
        $this->createSessionRow($userB, 'user-b-session');

        $this->sessionInvalidationService->invalidateAllSessions($userA);

        self::assertSame([], $this->findSessionIdsForUser($userA));
        self::assertSame(['user-b-session'], $this->findSessionIdsForUser($userB));

        $this->cleanupUser($userA);
        $this->cleanupUser($userB);
    }

    /**
     * @return list<string>
     */
    private function findSessionIdsForUser(User $user): array
    {
        if (null === $user->id) {
            throw new \LogicException('User must be persisted before querying its sessions.');
        }

        /** @var list<string> $sessionIds */
        $sessionIds = $this->connection->fetchFirstColumn(
            'SELECT session_id FROM sessions WHERE user_id = :userId ORDER BY session_id ASC',
            [
                'userId' => $user->id->toRfc4122(),
            ],
        );

        return $sessionIds;
    }

    /**
     * Inserted via raw DBAL, like App\Security\Session\DoctrineSessionHandler does in
     * production — sessions are never written through the EntityManager.
     */
    private function createSessionRow(User $user, string $sessionId): string
    {
        if (null === $user->id) {
            throw new \LogicException('User must be persisted before creating a session for it.');
        }

        $this->connection->executeStatement(
            'INSERT INTO sessions (id, session_id, data, lifetime, updated_at, user_id) VALUES (:id, :sessionId, :data, :lifetime, :updatedAt, :userId)',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'sessionId' => $sessionId,
                'data' => 'foo=bar',
                'lifetime' => 1440,
                'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'userId' => $user->id->toRfc4122(),
            ],
        );

        return $sessionId;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'T' . substr(bin2hex(random_bytes(8)), 0, 16);
        $user->lastLogin = new \DateTimeImmutable();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanupUser(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
