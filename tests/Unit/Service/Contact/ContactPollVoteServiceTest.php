<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\ContactPollOption;
use App\Entity\ContactThread;
use App\Enum\Contact\ContactCategoryEnum;
use App\Exception\Contact\AlreadyVotedException;
use App\Service\Contact\ContactPollVoteService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ContactPollVoteServiceTest extends TestCase
{
    public function testVotePersistsTheVoteAndAttachesItToTheThread(): void
    {
        $thread = $this->createThread();
        $option = new ContactPollOption();
        $option->label = 'Option A';

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new ContactPollVoteService($em);
        $vote = $service->vote($thread, $option);

        self::assertSame($thread, $vote->thread);
        self::assertSame($option, $vote->option);
        self::assertSame($vote, $thread->pollVote);
    }

    public function testVoteConvertsAUniqueConstraintViolationIntoAlreadyVotedException(): void
    {
        $thread = $this->createThread();
        $option = new ContactPollOption();
        $option->label = 'Option A';

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(
            $this->createStub(UniqueConstraintViolationException::class),
        );

        $service = new ContactPollVoteService($em);

        $this->expectException(AlreadyVotedException::class);

        $service->vote($thread, $option);
    }

    private function createThread(): ContactThread
    {
        $thread = new ContactThread();
        $thread->category = ContactCategoryEnum::VOTE;
        $thread->subject = 'Sondage de test';

        return $thread;
    }
}
