<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Exercise;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Exercise|null>
 */
final class ExerciseVoter extends Voter
{
    use ResolvesAuthenticatedUserTrait;

    public const string CREATE = 'EXERCISE_CREATE';

    public const string EDIT = 'EXERCISE_EDIT';

    public const string DELETE = 'EXERCISE_DELETE';

    public const string RESTORE = 'EXERCISE_RESTORE';

    private const array SUPPORTED_ATTRIBUTES = [
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::RESTORE,
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
        $user = $this->resolveUser($token);

        if (null === $user) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => true,
            self::EDIT, self::DELETE, self::RESTORE => $subject instanceof Exercise && $subject->owner === $user,
            default => false,
        };
    }
}
