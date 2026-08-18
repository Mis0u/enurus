<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Routine;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Routine>
 */
final class RoutineVoter extends Voter
{
    use ResolvesAuthenticatedUserTrait;

    public const string CREATE = 'ROUTINE_CREATE';

    public const string EDIT = 'ROUTINE_EDIT';

    public const string DELETE = 'ROUTINE_DELETE';

    public const string VIEW = 'ROUTINE_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::CREATE === $attribute) {
            return true;
        }

        return in_array($attribute, [self::EDIT, self::DELETE, self::VIEW], true)
            && $subject instanceof Routine;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $this->resolveUser($token);

        if (null === $user) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return true;
        }

        /** @var Routine $subject */
        return $subject->owner === $user;
    }
}
