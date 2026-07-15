<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\UserRepository;
use App\Service\Contact\ContactThreadCloseService;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContactThreadCloseServiceTest extends KernelTestCase
{
    public function testCloseSetsStatusAndClosedAt(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ContactThreadCloseService $contactThreadCloseService */
        $contactThreadCloseService = static::getContainer()->get(ContactThreadCloseService::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $owner = $userRepository->findOneBy([
            'email' => UserFixtures::USER_DASHBOARD_SINGLE,
        ]);

        if (! $owner instanceof User) {
            throw new \LogicException('Fixture user not found.');
        }

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);

        self::assertSame(ContactThreadStatusEnum::AWAITING_ADMIN_REPLY, $thread->status);
        self::assertNull($thread->closedAt);

        $contactThreadCloseService->close($thread);

        self::assertSame(ContactThreadStatusEnum::CLOSED, $thread->status);
        self::assertNotNull($thread->closedAt);
    }
}
