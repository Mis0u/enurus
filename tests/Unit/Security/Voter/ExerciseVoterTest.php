<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Exercise;
use App\Entity\User;
use App\Security\Voter\ExerciseVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExerciseVoterTest extends TestCase
{
    private ExerciseVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ExerciseVoter();
    }

    public function testAnyAuthenticatedUserCanCreateAnExercise(): void
    {
        $user = $this->createUser('user@test.com');

        $vote = $this->voter->vote($this->tokenFor($user), null, [ExerciseVoter::CREATE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testAnonymousTokenCannotCreateAnExercise(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, null, [ExerciseVoter::CREATE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOwnerCanEditTheirExercise(): void
    {
        $owner = $this->createUser('owner@test.com');
        $exercise = $this->createExercise($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $exercise, [ExerciseVoter::EDIT]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCanDeleteTheirExercise(): void
    {
        $owner = $this->createUser('owner@test.com');
        $exercise = $this->createExercise($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $exercise, [ExerciseVoter::DELETE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOtherUserCannotEditTheExercise(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $exercise = $this->createExercise($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $exercise, [ExerciseVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testPublicExerciseWithNoOwnerCannotBeEditedByAnyUser(): void
    {
        $user = $this->createUser('user@test.com');
        $publicExercise = $this->createExercise(owner: null);

        $vote = $this->voter->vote($this->tokenFor($user), $publicExercise, [ExerciseVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testPublicExerciseWithNoOwnerCannotBeDeletedByAnyUser(): void
    {
        $user = $this->createUser('user@test.com');
        $publicExercise = $this->createExercise(owner: null);

        $vote = $this->voter->vote($this->tokenFor($user), $publicExercise, [ExerciseVoter::DELETE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAnonymousTokenIsDeniedOnEdit(): void
    {
        $owner = $this->createUser('owner@test.com');
        $exercise = $this->createExercise($owner);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, $exercise, [ExerciseVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');
        $exercise = $this->createExercise($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $exercise, ['EXERCISE_UNKNOWN']);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    public function testUnsupportedSubjectAbstainsOnEdit(): void
    {
        $owner = $this->createUser('owner@test.com');

        $vote = $this->voter->vote($this->tokenFor($owner), new \stdClass(), [ExerciseVoter::EDIT]);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    private function createExercise(?User $owner): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $exercise->owner = $owner;
        $exercise->isPublic = null === $owner;

        return $exercise;
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
