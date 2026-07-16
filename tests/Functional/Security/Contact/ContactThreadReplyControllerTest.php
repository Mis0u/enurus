<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\DataFixtures\UserFixtures;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\ContactThreadRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class ContactThreadReplyControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = 'user-fixture-2@test.com';

    private const string OTHER_USER = 'user-fixture-3@test.com';

    public function testOwnerCanReplyAndStatusFlipsToAwaitingAdmin(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);
        ContactThreadTestHelper::addAdminMessage($entityManager, $thread, $admin);

        $url = \sprintf('/fr/messagerie/%s', $thread->id);
        $crawler = $client->request(Request::METHOD_GET, $url);
        $token = $this->getCsrfToken($crawler);

        $client->request(Request::METHOD_POST, \sprintf('%s/repondre', $url), [
            'contact_reply_form' => [
                'message' => "Merci pour la réponse, j'ai une autre question.",
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects($url);

        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        $reloadedThread = $contactThreadRepository->find($thread->id);

        self::assertNotNull($reloadedThread);
        self::assertCount(3, $reloadedThread->messages);
        self::assertSame(ContactThreadStatusEnum::AWAITING_ADMIN_REPLY, $reloadedThread->status);
    }

    public function testNonOwnerCannotReply(): void
    {
        $client = $this->login(self::OTHER_USER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);
        $url = \sprintf('/fr/messagerie/%s', $thread->id);

        $client->request(Request::METHOD_POST, \sprintf('%s/repondre', $url), [
            'contact_reply_form' => [
                'message' => 'Une réponse qui ne devrait jamais être acceptée.',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testReplyOnClosedThreadIsForbidden(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::CLOSED);
        $url = \sprintf('/fr/messagerie/%s', $thread->id);

        $client->request(Request::METHOD_POST, \sprintf('%s/repondre', $url), [
            'contact_reply_form' => [
                'message' => 'Ce message ne devrait jamais être accepté sur un fil clôturé.',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRestrictedUserCannotReply(): void
    {
        $client = $this->login(UserFixtures::USER_RESTRICTED_ONE_MONTH);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(UserFixtures::USER_RESTRICTED_ONE_MONTH);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);
        $url = \sprintf('/fr/messagerie/%s', $thread->id);

        $client->request(Request::METHOD_POST, \sprintf('%s/repondre', $url), [
            'contact_reply_form' => [
                'message' => 'Ce message ne devrait jamais être accepté, cet utilisateur est restreint.',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function getCsrfToken(Crawler $crawler): string
    {
        return (string) $crawler->filter('#contact_reply_form__token')->attr('value');
    }
}
