<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\RegularityGoal;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, RegularityGoal>
 */
final class RegularityGoalVoter extends Voter
{
    use ResolvesAuthenticatedUserTrait;

    public const string DELETE = 'REGULARITY_GOAL_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof RegularityGoal;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $this->resolveUser($token);

        return null !== $user && $subject->owner === $user;
    }
}
