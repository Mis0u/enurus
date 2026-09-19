<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProfileConnectionPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /**
     * @var list<User>
     */
    private array $createdUsers = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    protected function tearDown(): void
    {
        if (! $this->entityManager->isOpen()) {
            $this->reopenEntityManager();
        }

        foreach ($this->createdUsers as $user) {
            if (null === $user->id) {
                continue;
            }

            $managed = $this->entityManager->find(User::class, $user->id);

            if ($managed instanceof User) {
                $this->entityManager->remove($managed);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testPersistedConnectionKeepsItsPartiesAndStatus(): void
    {
        $requester = $this->createUser('connection-requester@test.com');
        $addressee = $this->createUser('connection-addressee@test.com');

        $connection = $this->createConnection($requester, $addressee);
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(ProfileConnection::class, $connection->id);

        self::assertInstanceOf(ProfileConnection::class, $reloaded);
        self::assertSame($requester->id?->toRfc4122(), $reloaded->requester->id?->toRfc4122());
        self::assertSame($addressee->id?->toRfc4122(), $reloaded->addressee->id?->toRfc4122());
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $reloaded->status);
        self::assertNull($reloaded->respondedAt);
    }

    public function testSameRequesterAndAddresseePairCannotBePersistedTwice(): void
    {
        $requester = $this->createUser('duplicate-requester@test.com');
        $addressee = $this->createUser('duplicate-addressee@test.com');
        $this->createConnection($requester, $addressee);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createConnection($requester, $addressee);
    }

    public function testDeletingRequesterRemovesItsConnectionsButKeepsTheOtherParty(): void
    {
        $requester = $this->createUser('cascade-requester@test.com');
        $addressee = $this->createUser('cascade-addressee@test.com');
        $connection = $this->createConnection($requester, $addressee);
        $connectionId = $connection->id;

        $this->entityManager->refresh($requester);
        $this->entityManager->remove($requester);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(ProfileConnection::class, $connectionId));
        self::assertInstanceOf(User::class, $this->entityManager->find(User::class, $addressee->id));
    }

    public function testDeletingAddresseeRemovesItsConnectionsButKeepsTheOtherParty(): void
    {
        $requester = $this->createUser('cascade-received-requester@test.com');
        $addressee = $this->createUser('cascade-received-addressee@test.com');
        $connection = $this->createConnection($requester, $addressee);
        $connectionId = $connection->id;

        $this->entityManager->refresh($addressee);
        $this->entityManager->remove($addressee);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(ProfileConnection::class, $connectionId));
        self::assertInstanceOf(User::class, $this->entityManager->find(User::class, $requester->id));
    }

    public function testTwoUsersCannotShareTheSameShareCode(): void
    {
        $first = $this->createUser('share-code-first@test.com');
        $second = $this->createUser('share-code-second@test.com');
        $first->shareCode = 'DUP4T3';
        $this->entityManager->flush();

        $second->shareCode = 'DUP4T3';

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testManyUsersWithoutShareCodeCanCoexist(): void
    {
        $this->createUser('no-share-code-one@test.com');
        $this->createUser('no-share-code-two@test.com');

        $this->entityManager->flush();

        $this->addToAssertionCount(1);
    }

    private function reopenEntityManager(): void
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get(ManagerRegistry::class);
        $registry->resetManager();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'SameNickname';
        $user->lastLogin = new \DateTimeImmutable();

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->createdUsers[] = $user;

        return $user;
    }

    private function createConnection(User $requester, User $addressee): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }
}
