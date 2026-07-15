<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\Entity\User;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class ContactThreadDeleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = 'user-fixture-2@test.com';

    private const string OTHER_USER = 'user-fixture-3@test.com';

    public function testOwnerCanHideOwnThread(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);
        $showUrl = \sprintf('/fr/messagerie/%s', $thread->id);
        $crawler = $client->request(Request::METHOD_GET, $showUrl);
        $token = $this->getDeleteCsrfToken($crawler);

        $client->request(
            Request::METHOD_DELETE,
            \sprintf('%s/supprimer', $showUrl),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_X-CSRF-Token' => $token,
            ],
        );

        self::assertResponseIsSuccessful();

        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        $reloadedThread = $contactThreadRepository->find($thread->id);

        self::assertNotNull($reloadedThread);
        self::assertNotNull($reloadedThread->hiddenByUserAt);
    }

    public function testHiddenThreadDisappearsFromList(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner, subject: 'Fil qui va être masqué');
        $showUrl = \sprintf('/fr/messagerie/%s', $thread->id);
        $crawler = $client->request(Request::METHOD_GET, $showUrl);
        $token = $this->getDeleteCsrfToken($crawler);

        $client->request(
            Request::METHOD_DELETE,
            \sprintf('%s/supprimer', $showUrl),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_X-CSRF-Token' => $token,
            ],
        );

        $client->request(Request::METHOD_GET, '/fr/messagerie');

        self::assertSelectorTextNotContains('body', 'Fil qui va être masqué');
    }

    public function testNonOwnerCannotHideThread(): void
    {
        $client = $this->login(self::OTHER_USER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);

        $client->request(
            Request::METHOD_DELETE,
            \sprintf('/fr/messagerie/%s/supprimer', $thread->id),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_X-CSRF-Token' => 'irrelevant-blocked-before-csrf-check',
            ],
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonXhrRequestIsRejected(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);

        $client->request(Request::METHOD_DELETE, \sprintf('/fr/messagerie/%s/supprimer', $thread->id));

        self::assertResponseStatusCodeSame(400);
    }

    private function getDeleteCsrfToken(Crawler $crawler): string
    {
        return (string) $crawler->filter('button[data-contact--delete-csrf-token-value]')->attr('data-contact--delete-csrf-token-value');
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
