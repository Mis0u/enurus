<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Routine;
use App\Entity\User;
use App\Security\Voter\RoutineVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RoutineVoterTest extends TestCase
{
    private RoutineVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new RoutineVoter();
    }

    public function testAnyAuthenticatedUserCanCreateARoutine(): void
    {
        $user = $this->createUser('user@test.com');

        $vote = $this->voter->vote($this->tokenFor($user), null, [RoutineVoter::CREATE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testAnonymousTokenCannotCreateARoutine(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, null, [RoutineVoter::CREATE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOwnerCanEditTheirRoutine(): void
    {
        $owner = $this->createUser('owner@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $routine, [RoutineVoter::EDIT]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCanDeleteTheirRoutine(): void
    {
        $owner = $this->createUser('owner@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $routine, [RoutineVoter::DELETE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOtherUserCannotEditTheRoutine(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $routine, [RoutineVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOtherUserCannotDeleteTheRoutine(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $routine, [RoutineVoter::DELETE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOwnerCanViewTheirRoutine(): void
    {
        $owner = $this->createUser('owner@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $routine, [RoutineVoter::VIEW]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOtherUserCannotViewTheRoutine(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $routine, [RoutineVoter::VIEW]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAnonymousTokenIsDeniedOnView(): void
    {
        $owner = $this->createUser('owner@test.com');
        $routine = $this->createRoutine($owner);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, $routine, [RoutineVoter::VIEW]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAnonymousTokenIsDeniedOnEdit(): void
    {
        $owner = $this->createUser('owner@test.com');
        $routine = $this->createRoutine($owner);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, $routine, [RoutineVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');
        $routine = $this->createRoutine($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $routine, ['ROUTINE_UNKNOWN']);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    public function testUnsupportedSubjectAbstainsOnEdit(): void
    {
        $owner = $this->createUser('owner@test.com');

        $vote = $this->voter->vote($this->tokenFor($owner), new \stdClass(), [RoutineVoter::EDIT]);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    private function createRoutine(User $owner): Routine
    {
        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Push day';

        return $routine;
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
