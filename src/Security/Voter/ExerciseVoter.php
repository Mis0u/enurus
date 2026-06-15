<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Exercise;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Exercise|null>
 */
final class ExerciseVoter extends Voter
{
    public const string CREATE = 'EXERCISE_CREATE';

    public const string EDIT = 'EXERCISE_EDIT';

    public const string DELETE = 'EXERCISE_DELETE';

    private const array SUPPORTED_ATTRIBUTES = [
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (! in_array($attribute, self::SUPPORTED_ATTRIBUTES, strict: true)) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return true;
        }

        return $subject instanceof Exercise;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => true,
            self::EDIT, self::DELETE => $subject instanceof Exercise && $this->isOwner($subject, $user),
            default => false,
        };
    }

    private function isOwner(Exercise $exercise, User $user): bool
    {
        return $exercise->owner === $user;
    }
}
