<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\User;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use App\Repository\UserRepository;
use App\Service\ProfileSharing\ProfileLookupService;
use App\Service\ProfileSharing\ProfileSharingRateLimitGuard;
use App\Service\Security\RateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ProfileLookupServiceTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    private const int SEARCH_LIMIT = 3;

    private RateLimiterFactory $limiterFactory;

    protected function setUp(): void
    {
        $this->limiterFactory = new RateLimiterFactory([
            'id' => 'profile_search_test',
            'policy' => 'sliding_window',
            'limit' => self::SEARCH_LIMIT,
            'interval' => '15 minutes',
        ], new InMemoryStorage());
    }

    public function testFindsTheUserMatchingNicknameAndCode(): void
    {
        $target = $this->createSearchableUser('Misou', 'A7K2XM');
        $searcher = $this->createSearchableUser('Other', 'B8L3YN');

        $found = $this->createService($target)->find($searcher, 'Misou#A7K2XM');

        self::assertSame($target, $found);
    }

    public function testNicknameIsComparedIgnoringCase(): void
    {
        $target = $this->createSearchableUser('Misou', 'A7K2XM');

        $found = $this->createService($target)->find($this->createSearchableUser('Other', 'B8L3YN'), 'MISOU#a7k2xm');

        self::assertSame($target, $found);
    }

    public function testReturnsNullWhenNoUserOwnsTheCode(): void
    {
        $searcher = $this->createSearchableUser('Other', 'B8L3YN');

        self::assertNull($this->createService(null)->find($searcher, 'Misou#A7K2XM'));
    }

    public function testReturnsNullWhenTheNicknameDoesNotMatchTheCodeOwner(): void
    {
        $target = $this->createSearchableUser('Misou', 'A7K2XM');
        $searcher = $this->createSearchableUser('Other', 'B8L3YN');

        self::assertNull($this->createService($target)->find($searcher, 'Someone#A7K2XM'));
    }

    public function testReturnsNullWhenTheOwnerIsNotSearchable(): void
    {
        $target = $this->createSearchableUser('Misou', 'A7K2XM');
        $target->isDiscoverable = false;
        $searcher = $this->createSearchableUser('Other', 'B8L3YN');

        self::assertNull($this->createService($target)->find($searcher, 'Misou#A7K2XM'));
    }

    public function testReturnsNullWhenSearchingOneself(): void
    {
        $searcher = $this->createSearchableUser('Misou', 'A7K2XM');

        self::assertNull($this->createService($searcher)->find($searcher, 'Misou#A7K2XM'));
    }

    public function testMalformedQueryNeverReachesTheRepository(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findOneByShareCode');

        $service = new ProfileLookupService(
            $userRepository,
            new ProfileSharingRateLimitGuard(new RateLimiterService()),
            $this->limiterFactory,
        );

        self::assertNull($service->find($this->createSearchableUser('Other', 'B8L3YN'), 'not a valid query'));
    }

    public function testSearchesBeyondTheLimitThrow(): void
    {
        $service = $this->createService(null);
        $searcher = $this->createSearchableUser('Other', 'B8L3YN');

        for ($search = 0; self::SEARCH_LIMIT > $search; ++$search) {
            $service->find($searcher, 'Misou#A7K2XM');
        }

        $this->expectException(TooManyProfileSharingAttemptsException::class);

        $service->find($searcher, 'Misou#A7K2XM');
    }

    public function testMalformedQueriesAlsoCountTowardsTheLimit(): void
    {
        $service = $this->createService(null);
        $searcher = $this->createSearchableUser('Other', 'B8L3YN');

        for ($search = 0; self::SEARCH_LIMIT > $search; ++$search) {
            $service->find($searcher, 'garbage');
        }

        $this->expectException(TooManyProfileSharingAttemptsException::class);

        $service->find($searcher, 'garbage');
    }

    private function createService(?User $userOwningTheCode): ProfileLookupService
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByShareCode')->willReturn($userOwningTheCode);

        return new ProfileLookupService(
            $userRepository,
            new ProfileSharingRateLimitGuard(new RateLimiterService()),
            $this->limiterFactory,
        );
    }
}
