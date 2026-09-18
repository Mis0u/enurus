<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\ProfileConnection;
use App\Entity\User;
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

    private function connect(User $requester, User $addressee): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }
}
