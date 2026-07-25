<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\ContactBroadcast;
use App\Entity\ContactPollVote;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Security\Voter\ContactThreadVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ContactThreadVoterTest extends TestCase
{
    private ContactThreadVoter $voter;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-01 12:00:00');
        $this->voter = new ContactThreadVoter($this->clock);
    }

    public function testOwnerCanReplyToRegularThread(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createThread($owner, ContactCategoryEnum::BUG);

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::REPLY]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCannotReplyToInformativeThread(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createThread($owner, ContactCategoryEnum::INFORMATIVE);

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::REPLY]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOwnerCannotReplyToClosedThread(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createThread($owner, ContactCategoryEnum::BUG);
        $thread->status = ContactThreadStatusEnum::CLOSED;

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::REPLY]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testAdminCanAlwaysViewInformativeThread(): void
    {
        $owner = $this->createOwner();
        $admin = $this->createAdmin();
        $thread = $this->createThread($owner, ContactCategoryEnum::INFORMATIVE);

        $vote = $this->voter->vote($this->tokenFor($admin), $thread, [ContactThreadVoter::VIEW]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCannotReplyToVoteThread(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createThread($owner, ContactCategoryEnum::VOTE);

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::REPLY]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOwnerCanVoteOnOpenPollNotYetVoted(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createVoteThread($owner, closesAt: '2026-02-01 00:00:00');

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::VOTE]);

        self::assertSame(Voter::ACCESS_GRANTED, $vote);
    }

    public function testOwnerCannotVoteTwice(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createVoteThread($owner, closesAt: '2026-02-01 00:00:00');
        $thread->pollVote = new ContactPollVote();

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::VOTE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    public function testOwnerCannotVoteAfterPollClosed(): void
    {
        $owner = $this->createOwner();
        $thread = $this->createVoteThread($owner, closesAt: '2025-12-01 00:00:00');

        $vote = $this->voter->vote($this->tokenFor($owner), $thread, [ContactThreadVoter::VOTE]);

        self::assertSame(Voter::ACCESS_DENIED, $vote);
    }

    private function createVoteThread(User $owner, string $closesAt): ContactThread
    {
        $thread = $this->createThread($owner, ContactCategoryEnum::VOTE);

        $broadcast = new ContactBroadcast();
        $broadcast->category = ContactCategoryEnum::VOTE;
        $broadcast->pollClosesAt = new \DateTimeImmutable($closesAt);
        $thread->broadcast = $broadcast;

        return $thread;
    }

    private function createThread(User $owner, ContactCategoryEnum $category): ContactThread
    {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = $category;
        $thread->subject = 'Sujet de test';

        $message = new ContactThreadMessage();
        $message->author = $owner;
        $message->body = 'Message de test suffisamment long.';
        $thread->addMessage($message);

        return $thread;
    }

    private function createOwner(): User
    {
        $user = new User();
        $user->email = 'owner@test.com';
        $user->password = 'hashed';
        $user->nickname = 'Owner';
        $user->lastLogin = new \DateTimeImmutable();

        return $user;
    }

    private function createAdmin(): User
    {
        $user = new User();
        $user->email = 'admin@test.com';
        $user->password = 'hashed';
        $user->nickname = 'Admin';
        $user->lastLogin = new \DateTimeImmutable();
        $user->setRoles(['ROLE_ADMIN']);

        return $user;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
