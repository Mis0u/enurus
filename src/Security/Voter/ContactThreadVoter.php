<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, ContactThread>
 */
final class ContactThreadVoter extends Voter
{
    use ResolvesAuthenticatedUserTrait;

    public const string VIEW = 'CONTACT_THREAD_VIEW';

    public const string REPLY = 'CONTACT_THREAD_REPLY';

    public const string CLOSE = 'CONTACT_THREAD_CLOSE';

    public const string DELETE = 'CONTACT_THREAD_DELETE';

    public const string VOTE = 'CONTACT_THREAD_VOTE';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::REPLY, self::CLOSE, self::DELETE, self::VOTE], true)
            && $subject instanceof ContactThread;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $this->resolveUser($token);

        if (null === $user) {
            return false;
        }

        /** @var ContactThread $subject */
        return match ($attribute) {
            self::VIEW => (null === $subject->hiddenByUserAt && $subject->owner === $user)
                || $this->isAdmin($user),
            self::REPLY => $subject->owner === $user
                && ContactThreadStatusEnum::CLOSED !== $subject->status
                && ContactCategoryEnum::INFORMATIVE !== $subject->category
                && ContactCategoryEnum::VOTE !== $subject->category
                && ! $user->isContactRestricted,
            self::CLOSE => $this->isAdmin($user),
            self::DELETE => $subject->owner === $user,
            self::VOTE => $this->canVote($subject, $user),
            default => false,
        };
    }

    private function canVote(ContactThread $thread, User $user): bool
    {
        return $thread->owner === $user
            && ContactCategoryEnum::VOTE === $thread->category
            && null === $thread->pollVote
            && null !== $thread->broadcast
            && ! $thread->broadcast->isPollClosed($this->clock->now());
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
