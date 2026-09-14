<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Security\Voter\ExerciseGoalVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExerciseGoalVoterTest extends TestCase
{
    private ExerciseGoalVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ExerciseGoalVoter();
    }

    public function testOwnerCanEditTheirGoal(): void
    {
        $owner = $this->createUser('owner@test.com');
        $goal = $this->createGoal($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $goal, [ExerciseGoalVoter::EDIT]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCanDeleteTheirGoal(): void
    {
        $owner = $this->createUser('owner@test.com');
        $goal = $this->createGoal($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $goal, [ExerciseGoalVoter::DELETE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOtherUserCannotEditSomeoneElsesGoal(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $goal = $this->createGoal($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $goal, [ExerciseGoalVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOtherUserCannotDeleteSomeoneElsesGoal(): void
    {
        $owner = $this->createUser('owner@test.com');
        $other = $this->createUser('other@test.com');
        $goal = $this->createGoal($owner);

        $vote = $this->voter->vote($this->tokenFor($other), $goal, [ExerciseGoalVoter::DELETE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAnonymousTokenIsDenied(): void
    {
        $owner = $this->createUser('owner@test.com');
        $goal = $this->createGoal($owner);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $vote = $this->voter->vote($token, $goal, [ExerciseGoalVoter::EDIT]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testUnsupportedSubjectAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');

        $vote = $this->voter->vote($this->tokenFor($owner), new \stdClass(), [ExerciseGoalVoter::EDIT]);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $owner = $this->createUser('owner@test.com');
        $goal = $this->createGoal($owner);

        $vote = $this->voter->vote($this->tokenFor($owner), $goal, ['GOAL_UNKNOWN']);

        self::assertSame(Voter::ACCESS_ABSTAIN, $vote);
    }

    private function createGoal(User $owner): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $owner;
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = 100.0;

        return $goal;
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
