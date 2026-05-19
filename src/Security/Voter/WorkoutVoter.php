<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\Workout;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Workout>
 */
final class WorkoutVoter extends Voter
{
    public const string VIEW = 'WORKOUT_VIEW';

    public const string EDIT = 'WORKOUT_EDIT';

    public const string DELETE = 'WORKOUT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Workout;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof User) {
            return false;
        }

        /** @var Workout $subject */
        return match ($attribute) {
            self::VIEW, self::EDIT, self::DELETE => $subject->owner === $user,
            default => false,
        };
    }
}
