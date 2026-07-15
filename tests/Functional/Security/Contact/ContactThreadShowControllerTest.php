<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\ContactThreadMessageRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContactThreadShowControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = 'user-fixture-2@test.com';

    private const string OTHER_USER = 'user-fixture-3@test.com';

    public function testOwnerCanViewTheirThread(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner, 'Fil de test visible');

        $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Fil de test visible');
        self::assertSelectorExists('form[name="contact_reply_form"]');
    }

    public function testNonOwnerCannotViewThread(): void
    {
        $client = $this->login(self::OTHER_USER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);

        $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        self::assertResponseStatusCodeSame(403);
    }

    public function testViewingThreadMarksAdminMessagesAsRead(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);
        ContactThreadTestHelper::addAdminMessage($entityManager, $thread, $admin);

        /** @var ContactThreadMessageRepository $contactThreadMessageRepository */
        $contactThreadMessageRepository = static::getContainer()->get(ContactThreadMessageRepository::class);
        self::assertSame(1, $contactThreadMessageRepository->countUnreadForUser($owner));

        $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        self::assertSame(0, $contactThreadMessageRepository->countUnreadForUser($owner));
    }

    public function testReplyFormIsHiddenWhenClosed(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread(
            $entityManager,
            $owner,
            status: ContactThreadStatusEnum::CLOSED,
        );

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        self::assertSelectorNotExists('form[name="contact_reply_form"]');
        self::assertStringContainsString('clôturé', $crawler->filter('body')->text());
    }

    public function testReplyFormIsHiddenWhenRestricted(): void
    {
        $client = $this->login(UserFixtures::USER_RESTRICTED_ONE_WEEK);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(UserFixtures::USER_RESTRICTED_ONE_WEEK);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        self::assertSelectorNotExists('form[name="contact_reply_form"]');
        self::assertStringContainsString('restreint', $crawler->filter('body')->text());
    }

    private function getUserByEmail(string $email): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        if (! $user instanceof User) {
            throw new \LogicException(\sprintf('Fixture user "%s" not found.', $email));
        }

        return $user;
    }
}
