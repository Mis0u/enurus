<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Security\Voter\ProfileConnectionVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProfileConnectionVoterTest extends TestCase
{
    private ProfileConnectionVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ProfileConnectionVoter();
    }

    public function testRequesterCanViewTheDashboardOfAnAcceptedConnection(): void
    {
        $connection = $this->createConnection($requester = new User(), new User(), ProfileConnectionStatusEnum::ACCEPTED);

        self::assertSame(Voter::ACCESS_GRANTED, $this->voteView($requester, $connection));
    }

    public function testAddresseeCanViewTheDashboardOfAnAcceptedConnection(): void
    {
        $connection = $this->createConnection(new User(), $addressee = new User(), ProfileConnectionStatusEnum::ACCEPTED);

        self::assertSame(Voter::ACCESS_GRANTED, $this->voteView($addressee, $connection));
    }

    /**
     * @return iterable<string, array{ProfileConnectionStatusEnum}>
     */
    public static function notAcceptedStatuses(): iterable
    {
        yield 'pending' => [ProfileConnectionStatusEnum::PENDING];
        yield 'declined' => [ProfileConnectionStatusEnum::DECLINED];
        yield 'revoked' => [ProfileConnectionStatusEnum::REVOKED];
    }

    #[DataProvider('notAcceptedStatuses')]
    public function testNeitherPartyCanViewTheDashboardOfAConnectionThatIsNotAccepted(ProfileConnectionStatusEnum $status): void
    {
        $connection = $this->createConnection($requester = new User(), $addressee = new User(), $status);

        self::assertSame(Voter::ACCESS_DENIED, $this->voteView($requester, $connection));
        self::assertSame(Voter::ACCESS_DENIED, $this->voteView($addressee, $connection));
    }

    public function testAStrangerCannotViewTheDashboardEvenOfAnAcceptedConnection(): void
    {
        $connection = $this->createConnection(new User(), new User(), ProfileConnectionStatusEnum::ACCEPTED);

        self::assertSame(Voter::ACCESS_DENIED, $this->voteView(new User(), $connection));
    }

    public function testAnonymousTokenCannotViewAnyDashboard(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $connection = $this->createConnection(new User(), new User(), ProfileConnectionStatusEnum::ACCEPTED);

        self::assertSame(Voter::ACCESS_DENIED, $this->voter->vote($token, $connection, [ProfileConnectionVoter::VIEW]));
    }

    public function testAbstainsOnAnUnsupportedAttribute(): void
    {
        $connection = $this->createConnection($requester = new User(), new User(), ProfileConnectionStatusEnum::ACCEPTED);

        self::assertSame(Voter::ACCESS_ABSTAIN, $this->voter->vote($this->tokenFor($requester), $connection, ['SOMETHING_ELSE']));
    }

    public function testAbstainsOnAnUnsupportedSubject(): void
    {
        self::assertSame(Voter::ACCESS_ABSTAIN, $this->voter->vote($this->tokenFor(new User()), new User(), [ProfileConnectionVoter::VIEW]));
    }

    private function voteView(User $user, ProfileConnection $connection): int
    {
        return $this->voter->vote($this->tokenFor($user), $connection, [ProfileConnectionVoter::VIEW]);
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createConnection(User $requester, User $addressee, ProfileConnectionStatusEnum $status): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = $status;

        return $connection;
    }
}
