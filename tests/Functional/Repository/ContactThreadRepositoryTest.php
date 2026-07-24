<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContactThreadRepositoryTest extends KernelTestCase
{
    public function testCountAwaitingAdminReplyExcludesAnsweredClosedAndBroadcastThreads(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $owner = $userRepository->findOneBy([
            'email' => 'user-fixture-0@test.com',
        ]);

        if (! $owner instanceof User) {
            throw new \LogicException('Fixture user "user-fixture-0@test.com" not found.');
        }

        $before = $contactThreadRepository->countAwaitingAdminReply();

        $awaiting = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::AWAITING_ADMIN_REPLY);
        $answered = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::ANSWERED_BY_ADMIN);
        $closed = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::CLOSED);

        self::assertSame($before + 1, $contactThreadRepository->countAwaitingAdminReply());

        $entityManager->remove($awaiting);
        $entityManager->remove($answered);
        $entityManager->remove($closed);
        $entityManager->flush();
    }
}
