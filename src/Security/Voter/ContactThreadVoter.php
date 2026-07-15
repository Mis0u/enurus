<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, ContactThread>
 */
final class ContactThreadVoter extends Voter
{
    public const string VIEW = 'CONTACT_THREAD_VIEW';

    public const string REPLY = 'CONTACT_THREAD_REPLY';

    public const string CLOSE = 'CONTACT_THREAD_CLOSE';

    public const string DELETE = 'CONTACT_THREAD_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::REPLY, self::CLOSE, self::DELETE], true)
            && $subject instanceof ContactThread;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof User) {
            return false;
        }

        /** @var ContactThread $subject */
        return match ($attribute) {
            self::VIEW => (null === $subject->hiddenByUserAt && $subject->owner === $user)
                || in_array('ROLE_ADMIN', $user->getRoles(), true),
            self::REPLY => $subject->owner === $user
                && ContactThreadStatusEnum::CLOSED !== $subject->status
                && ! $user->isContactRestricted,
            self::CLOSE => in_array('ROLE_ADMIN', $user->getRoles(), true),
            self::DELETE => $subject->owner === $user,
            default => false,
        };
    }
}
