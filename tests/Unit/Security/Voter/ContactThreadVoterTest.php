<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Security\Voter\ContactThreadVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ContactThreadVoterTest extends TestCase
{
    private ContactThreadVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ContactThreadVoter();
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
