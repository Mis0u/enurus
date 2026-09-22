<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DeloadPeriod;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, DeloadPeriod>
 */
final class DeloadPeriodVoter extends Voter
{
    use ResolvesAuthenticatedUserTrait;

    public const string DELETE = 'DELOAD_PERIOD_DELETE';

    private const array SUPPORTED_ATTRIBUTES = [
        self::DELETE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, strict: true) && $subject instanceof DeloadPeriod;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $this->resolveUser($token);

        if (null === $user) {
            return false;
        }

        return match ($attribute) {
            self::DELETE => $subject->owner === $user,
            default => false,
        };
    }
}
