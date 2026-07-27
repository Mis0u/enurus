<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Entity\Workout;
use App\Security\Voter\WorkoutVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class WorkoutVoterTest extends TestCase
{
    private WorkoutVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new WorkoutVoter();
    }

    public function testOwnerCanViewTheirWorkout(): void
    {
        $owner = $this->createUser('owner@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $workout, [WorkoutVoter::VIEW]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCanEditTheirWorkout(): void
    {
        $owner = $this->createUser('owner@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $workout, [WorkoutVoter::EDIT]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCanDeleteTheirWorkout(): void
    {
        $owner = $this->createUser('owner@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $workout, [WorkoutVoter::DELETE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOtherUserCannotViewTheWorkout(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $workout, [WorkoutVoter::VIEW]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOtherUserCannotEditTheWorkout(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $workout, [WorkoutVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOtherUserCannotDeleteTheWorkout(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $workout, [WorkoutVoter::DELETE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAnonymousTokenIsDenied(): void
    {
        $owner = $this->createUser('owner@test.com');
        $workout = $this->createWorkout($owner);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, $workout, [WorkoutVoter::VIEW]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');
        $workout = $this->createWorkout($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $workout, ['WORKOUT_UNKNOWN']);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    public function testUnsupportedSubjectAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');

        $vote = $this->voter->vote($this->tokenFor($owner), new \stdClass(), [WorkoutVoter::VIEW]);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    private function createWorkout(User $owner): Workout
    {
        $workout = new Workout();
        $workout->owner = $owner;

        return $workout;
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
