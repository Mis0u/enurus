<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\DeloadPeriod;
use App\Entity\User;
use App\Security\Voter\DeloadPeriodVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class DeloadPeriodVoterTest extends TestCase
{
    private DeloadPeriodVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new DeloadPeriodVoter();
    }

    public function testOwnerCanDeleteTheirPeriod(): void
    {
        $owner = $this->createUser('owner@test.com');
        $deloadPeriod = $this->createDeloadPeriod($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $deloadPeriod, [DeloadPeriodVoter::DELETE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOtherUserCannotDeleteSomeoneElsesPeriod(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $deloadPeriod = $this->createDeloadPeriod($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $deloadPeriod, [DeloadPeriodVoter::DELETE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAnonymousTokenIsDenied(): void
    {
        $owner = $this->createUser('owner@test.com');
        $deloadPeriod = $this->createDeloadPeriod($owner);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, $deloadPeriod, [DeloadPeriodVoter::DELETE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testUnsupportedSubjectAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');

        $vote = $this->voter->vote($this->tokenFor($owner), new \stdClass(), [DeloadPeriodVoter::DELETE]);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');
        $deloadPeriod = $this->createDeloadPeriod($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $deloadPeriod, ['DELOAD_PERIOD_UNKNOWN']);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    private function createDeloadPeriod(User $owner): DeloadPeriod
    {
        $deloadPeriod = new DeloadPeriod();
        $deloadPeriod->owner = $owner;
        $deloadPeriod->startDate = new \DateTimeImmutable('today');
        $deloadPeriod->endDate = new \DateTimeImmutable('today');

        return $deloadPeriod;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'User';
        $user->lastLogin = new \DateTimeImmutable();

        return $user;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
