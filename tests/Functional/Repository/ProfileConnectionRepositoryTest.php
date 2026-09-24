<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\ProfileConnectionRepository;
use App\Tests\Functional\Helper\ProfileSharingTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProfileConnectionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ProfileConnectionRepository $repository;

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

        /** @var ProfileConnectionRepository $repository */
        $repository = static::getContainer()->get(ProfileConnectionRepository::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $user) {
            $this->entityManager->refresh($user);
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testFindBetweenFindsTheConnectionInTheRequestedDirection(): void
    {
        [$requester, $addressee] = $this->createPair('direct');
        $connection = $this->connect($requester, $addressee);

        self::assertSame($connection, $this->repository->findBetween($requester, $addressee));
    }

    public function testFindBetweenFindsTheConnectionInTheOppositeDirection(): void
    {
        [$requester, $addressee] = $this->createPair('opposite');
        $connection = $this->connect($requester, $addressee);

        self::assertSame($connection, $this->repository->findBetween($addressee, $requester));
    }

    public function testFindBetweenIgnoresConnectionsOfOtherPairs(): void
    {
        [$requester, $addressee] = $this->createPair('paired');
        $stranger = $this->createUser('stranger', 'S7R4NG');
        $this->connect($requester, $addressee);

        self::assertNull($this->repository->findBetween($requester, $stranger));
        self::assertNull($this->repository->findBetween($stranger, $addressee));
    }

    public function testFindActiveInvolvingReturnsPendingAndAcceptedConnectionsOfBothRoles(): void
    {
        $me = $this->createUser('active-me', 'A2M3E4');
        $sent = $this->connect($me, $this->createUser('active-sent', 'A5S6E7'));
        $received = $this->connect($this->createUser('active-received', 'A8R9E2'), $me, ProfileConnectionStatusEnum::ACCEPTED);

        $found = $this->repository->findActiveInvolving($me);

        self::assertCount(2, $found);
        self::assertContains($sent, $found);
        self::assertContains($received, $found);
    }

    public function testFindActiveInvolvingIgnoresEndedConnectionsAndOtherUsers(): void
    {
        $me = $this->createUser('ignore-me', 'I2M3E4');
        $this->connect($me, $this->createUser('ignore-declined', 'I5D6E7'), ProfileConnectionStatusEnum::DECLINED);
        $this->connect($this->createUser('ignore-revoked', 'I8R9E2'), $me, ProfileConnectionStatusEnum::REVOKED);
        $this->connect($this->createUser('ignore-a', 'I3A4B5'), $this->createUser('ignore-b', 'I6B7C8'));

        self::assertSame([], $this->repository->findActiveInvolving($me));
    }

    public function testCountPendingReceivedByCountsOnlyPendingRequestsAddressedToTheUser(): void
    {
        $me = $this->createUser('count-me', 'C2M3E4');
        $this->connect($this->createUser('count-one', 'C5O6N7'), $me);
        $this->connect($this->createUser('count-two', 'C8T9W2'), $me);
        $this->connect($this->createUser('count-accepted', 'C3A4C5'), $me, ProfileConnectionStatusEnum::ACCEPTED);
        $this->connect($this->createUser('count-declined', 'C6D7E8'), $me, ProfileConnectionStatusEnum::DECLINED);
        $this->connect($me, $this->createUser('count-sent', 'C9S2E3'));

        self::assertSame(2, $this->repository->countPendingReceivedBy($me));
    }

    public function testCountPendingReceivedByIsZeroWithoutAnyRequest(): void
    {
        self::assertSame(0, $this->repository->countPendingReceivedBy($this->createUser('count-none', 'N2O3N4')));
    }

    public function testCountAcceptedInvolvingCountsAcceptedConnectionsOfBothRoles(): void
    {
        $me = $this->createUser('accepted-me', 'A2M3E4');
        $this->connect($this->createUser('accepted-in', 'A5I6N7'), $me, ProfileConnectionStatusEnum::ACCEPTED);
        $this->connect($me, $this->createUser('accepted-out', 'A8O9U2'), ProfileConnectionStatusEnum::ACCEPTED);
        $this->connect($this->createUser('accepted-pending', 'A3P4E5'), $me);
        $this->connect($me, $this->createUser('accepted-revoked', 'A6R7E8'), ProfileConnectionStatusEnum::REVOKED);

        self::assertSame(2, $this->repository->countAcceptedInvolving($me));
    }

    /**
     * @return array{User, User}
     */
    private function createPair(string $prefix): array
    {
        return [
            $this->createUser($prefix . '-requester', 'R3QST5'),
            $this->createUser($prefix . '-addressee', 'A5DRS6'),
        ];
    }

    private function createUser(string $name, string $shareCode): User
    {
        $user = ProfileSharingTestHelper::createSearchableUser(
            $this->entityManager,
            $name . '-repo@test.com',
            'Repo' . $name,
            $shareCode,
        );
        $this->createdUsers[] = $user;

        return $user;
    }

    private function connect(User $requester, User $addressee, ProfileConnectionStatusEnum $status = ProfileConnectionStatusEnum::PENDING): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = $status;

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }
}
