<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use App\Repository\ProfileConnectionRepository;
use App\Repository\UserRepository;
use App\Service\ProfileSharing\ProfileConnectionRequestService;
use App\Service\ProfileSharing\ProfileConnectionResponseService;
use App\Service\ProfileSharing\ProfileLookupService;
use App\Service\ProfileSharing\ProfileSharingRateLimitGuard;
use App\Service\Security\RateLimiterService;
use App\Tests\Functional\Helper\ProfileSharingTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Les services de partage de profil ne sont pas encore consommés par un controller : le conteneur
 * de test retire les services privés inutilisés, d'où l'assemblage à la main autour du vrai
 * EntityManager et des vrais repositories.
 */
final class ProfileConnectionLifecycleTest extends KernelTestCase
{
    private const string START = '2026-09-18 12:00:00';

    private EntityManagerInterface $entityManager;

    private ProfileConnectionRepository $connectionRepository;

    private MockClock $clock;

    private ProfileConnectionRequestService $requestService;

    private ProfileConnectionResponseService $responseService;

    private ProfileLookupService $lookupService;

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

        /** @var ProfileConnectionRepository $connectionRepository */
        $connectionRepository = static::getContainer()->get(ProfileConnectionRepository::class);
        $this->connectionRepository = $connectionRepository;

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $this->clock = new MockClock(self::START);
        $guard = new ProfileSharingRateLimitGuard(new RateLimiterService());

        $this->requestService = new ProfileConnectionRequestService(
            $this->connectionRepository,
            $this->entityManager,
            $guard,
            $this->createLimiterFactory('profile_connection_request'),
            $this->clock,
        );
        $this->responseService = new ProfileConnectionResponseService($this->entityManager, $this->clock);
        $this->lookupService = new ProfileLookupService(
            $userRepository,
            $guard,
            $this->createLimiterFactory('profile_search'),
        );
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();

        foreach ($this->createdUsers as $user) {
            $managed = $this->entityManager->find(User::class, $user->id);

            if ($managed instanceof User) {
                $this->entityManager->remove($managed);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testSearchThenRequestThenAcceptConnectsBothUsers(): void
    {
        $requester = $this->createUser('lifecycle-requester@test.com', 'LifeReq', 'K2M3N4');
        $addressee = $this->createUser('lifecycle-addressee@test.com', 'LifeAddr', 'P5Q6R7');

        $found = $this->lookupService->find($requester, 'LifeAddr#P5Q6R7');
        self::assertSame($addressee, $found);

        $this->requestBetween($requester, $addressee);
        $connection = $this->reloadConnection($requester, $addressee);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $connection->status);

        $this->responseService->accept($connection);

        $accepted = $this->reloadConnection($addressee, $requester);
        self::assertSame(ProfileConnectionStatusEnum::ACCEPTED, $accepted->status);
        self::assertNotNull($accepted->respondedAt);
    }

    public function testRevokedConnectionCanBeReopenedByTheOtherPartyAfterTheCooldown(): void
    {
        $first = $this->createUser('reopen-first@test.com', 'ReopenOne', 'S8T9U2');
        $second = $this->createUser('reopen-second@test.com', 'ReopenTwo', 'V3W4X5');
        $this->requestBetween($first, $second);
        $connection = $this->reloadConnection($first, $second);
        $this->responseService->accept($connection);
        $this->responseService->revoke($connection);

        $this->assertRequestFailsWith(ProfileConnectionFailureReasonEnum::COOLDOWN_ACTIVE, $second, $first);

        $this->clock->modify(\sprintf('+%d days', ProfileConnectionRequestService::RE_REQUEST_COOLDOWN_DAYS));
        $this->requestBetween($second, $first);

        $reopened = $this->reloadConnection($first, $second);
        self::assertSame($connection->id?->toRfc4122(), $reopened->id?->toRfc4122());
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $reopened->status);
        self::assertSame($second->id?->toRfc4122(), $reopened->requester->id?->toRfc4122());
        self::assertSame($first->id?->toRfc4122(), $reopened->addressee->id?->toRfc4122());
        self::assertNull($reopened->respondedAt);
    }

    public function testCancelledRequestLeavesNoTraceAndCanBeSentAgainAtOnce(): void
    {
        $requester = $this->createUser('cancel-requester@test.com', 'CancelReq', 'Y6Z7A8');
        $addressee = $this->createUser('cancel-addressee@test.com', 'CancelAdr', 'B9C2D3');
        $this->requestBetween($requester, $addressee);

        $this->responseService->cancel($this->reloadConnection($requester, $addressee));
        $this->entityManager->clear();

        self::assertNull($this->connectionRepository->findBetween($requester, $addressee));

        $this->requestBetween($requester, $addressee);

        self::assertInstanceOf(ProfileConnection::class, $this->connectionRepository->findBetween($requester, $addressee));
    }

    private function assertRequestFailsWith(ProfileConnectionFailureReasonEnum $expected, User $requester, User $addressee): void
    {
        try {
            $this->requestBetween($requester, $addressee);
            self::fail('Expected the request to be rejected.');
        } catch (ProfileConnectionException $exception) {
            self::assertSame($expected, $exception->reason);
        }
    }

    private function requestBetween(User $requester, User $addressee): void
    {
        $this->requestService->request($this->reloadUser($requester), $this->reloadUser($addressee));
    }

    private function reloadConnection(User $first, User $second): ProfileConnection
    {
        $this->entityManager->clear();

        $connection = $this->connectionRepository->findBetween(
            $this->reloadUser($first),
            $this->reloadUser($second),
        );

        return $connection ?? throw new \LogicException('Expected a connection between the two users.');
    }

    private function reloadUser(User $user): User
    {
        return $this->entityManager->find(User::class, $user->id) ?? throw new \LogicException('Expected the user to exist.');
    }

    private function createUser(string $email, string $nickname, string $shareCode): User
    {
        $user = ProfileSharingTestHelper::createSearchableUser($this->entityManager, $email, $nickname, $shareCode);
        $this->createdUsers[] = $user;

        return $user;
    }

    private function createLimiterFactory(string $id): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
    }
}
