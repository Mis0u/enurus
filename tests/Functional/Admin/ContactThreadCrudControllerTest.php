<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\ContactThreadCrudController;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContactThreadCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    private const string OWNER = 'user-fixture-0@test.com';

    public function testIndexListsThreadAndLinksToDetail(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(ContactThreadCrudController::class)->setAction('index')->generateUrl();

        $client->request(Request::METHOD_GET, $indexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="' . $this->actionUrl($client, $thread, 'detail') . '"]');
    }

    public function testIndexBoldsSubjectOnlyForThreadsAwaitingAdminReply(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $awaiting = $this->createThread(subject: 'En attente de réponse admin');
        $answered = ContactThreadTestHelper::createThread(
            $entityManager,
            $this->reloadUser(self::OWNER),
            subject: 'Déjà répondu',
            status: ContactThreadStatusEnum::ANSWERED_BY_ADMIN,
        );

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(ContactThreadCrudController::class)->setAction('index')->generateUrl();

        $crawler = $client->request(Request::METHOD_GET, $indexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('td[data-column="subject"] strong');
        self::assertStringContainsString('En attente de réponse admin', $crawler->filter('td[data-column="subject"] strong')->first()->text());

        $answeredRow = $crawler->filter('a[href="' . $this->actionUrl($client, $answered, 'detail') . '"]')->closest('tr');
        self::assertNotNull($answeredRow);
        self::assertCount(0, $answeredRow->filter('td[data-column="subject"] strong'));
        self::assertStringContainsString('Déjà répondu', $answeredRow->filter('td[data-column="subject"]')->text());
    }

    public function testIndexRendersCategoryAndStatusAsColoredBadges(): void
    {
        $client = $this->login(self::ADMIN);

        $this->createThread();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(ContactThreadCrudController::class)->setAction('index')->generateUrl();

        $client->request(Request::METHOD_GET, $indexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('td[data-column="category"] .badge-danger');
        self::assertSelectorExists('td[data-column="status"] .badge-warning');
    }

    public function testDeleteRemovesThread(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        $token = $this->csrfTokenFromPage($client, $this->actionUrl($client, $thread, 'detail'), 'input[name="token"]', 'value');

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $thread, 'delete'), [
            'token' => $token,
        ]);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        self::assertNull($entityManager->getRepository(ContactThread::class)->find($thread->id));
    }

    public function testBatchDeleteRemovesMultipleThreads(): void
    {
        $client = $this->login(self::ADMIN);

        $first = $this->createThread('Premier fil à supprimer');
        $second = $this->createThread('Second fil à supprimer');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(ContactThreadCrudController::class)->setAction('index')->generateUrl();

        $crawler = $client->request(Request::METHOD_GET, $indexUrl);
        $token = $crawler->filter('[data-action-csrf-token]')->first()->attr('data-action-csrf-token');

        self::assertNotNull($token);

        $batchDeleteUrl = $adminUrlGenerator
            ->setController(ContactThreadCrudController::class)
            ->setAction('batchDelete')
            ->generateUrl()
        ;

        $client->request(Request::METHOD_POST, $batchDeleteUrl, [
            'batchActionName' => 'batchDelete',
            'batchActionCsrfToken' => $token,
            'batchActionEntityIds' => [(string) $first->id, (string) $second->id],
            'entityFqcn' => ContactThread::class,
        ]);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $repository = $entityManager->getRepository(ContactThread::class);
        self::assertNull($repository->find($first->id));
        self::assertNull($repository->find($second->id));
    }

    public function testRenderFiltersDoesNotThrowOnOwnerFilter(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $filtersUrl = $adminUrlGenerator->setController(ContactThreadCrudController::class)->setAction('renderFilters')->generateUrl();

        $client->request(Request::METHOD_GET, $filtersUrl);

        self::assertResponseIsSuccessful();
    }

    public function testReplyCreatesAdminMessageAndUpdatesStatus(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $thread, 'reply'));
        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="contact_reply_form"]')->form([
            'contact_reply_form[message]' => 'Merci pour votre message, on regarde ça.',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        $reloaded = $this->reloadThread($thread);
        self::assertSame(ContactThreadStatusEnum::ANSWERED_BY_ADMIN, $reloaded->status);
        self::assertCount(2, $reloaded->messages);

        // `createdAt` est en précision seconde (TIMESTAMP(0) côté Postgres) — les deux messages de
        // ce test peuvent partager le même timestamp, donc ->last() n'est pas fiable ici, on
        // identifie le message admin par `fromAdmin` plutôt que par sa position dans la collection.
        $adminMessages = $reloaded->messages->filter(static fn (ContactThreadMessage $message): bool => $message->fromAdmin);
        self::assertCount(1, $adminMessages);

        $adminMessage = $adminMessages->first();
        self::assertInstanceOf(ContactThreadMessage::class, $adminMessage);
        self::assertSame('Merci pour votre message, on regarde ça.', $adminMessage->body);
    }

    public function testReplyWithImagePersistsImagePath(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $thread, 'reply'));
        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="contact_reply_form"]')->form([
            'contact_reply_form[message]' => 'Voici une capture pour illustrer la réponse.',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), [
            'image' => ImageTestHelper::createFakeImage('reply.jpg', 'image/jpeg'),
        ]);

        self::assertResponseRedirects();

        $reloaded = $this->reloadThread($thread);
        $adminMessage = $reloaded->messages->filter(static fn (ContactThreadMessage $message): bool => $message->fromAdmin)->first();
        self::assertInstanceOf(ContactThreadMessage::class, $adminMessage);
        self::assertNotNull($adminMessage->imagePath);

        $crawler = $client->request(Request::METHOD_GET, $this->actionUrl($client, $thread, 'detail'));

        self::assertSelectorExists('img[src="/uploads/' . $adminMessage->imagePath . '"]');
        self::assertStringNotContainsString('Pièce jointe :', $crawler->filter('body')->text());
    }

    public function testReplySanitizesAdminHtmlBodyStrippingScriptTags(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $thread, 'reply'));
        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="contact_reply_form"]')->form([
            'contact_reply_form[message]' => '<p>Réponse <strong>importante</strong></p><script>alert(1)</script>',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        $reloaded = $this->reloadThread($thread);
        $adminMessage = $reloaded->messages->filter(static fn (ContactThreadMessage $message): bool => $message->fromAdmin)->first();
        self::assertInstanceOf(ContactThreadMessage::class, $adminMessage);
        self::assertStringContainsString('<strong>importante</strong>', $adminMessage->body);
        self::assertStringNotContainsString('<script', $adminMessage->body);
    }

    public function testCloseSetsClosedStatus(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        $token = $this->csrfTokenFromPage($client, $this->actionUrl($client, $thread, 'close'), 'input[name="_token"]', 'value');

        $client->request(
            Request::METHOD_POST,
            $this->actionUrl($client, $thread, 'close'),
            [
                '_token' => $token,
            ],
        );

        self::assertResponseRedirects();

        $reloaded = $this->reloadThread($thread);
        self::assertSame(ContactThreadStatusEnum::CLOSED, $reloaded->status);
        self::assertNotNull($reloaded->closedAt);
    }

    public function testBlockPermanentlyRestrictsUser(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $thread, 'block'));
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Bloquer')->form([
            'contact_restriction_form[duration]' => 'permanent',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        $owner = $this->reloadUser(self::OWNER);
        self::assertTrue($owner->contactRestrictedPermanently);
        self::assertTrue($owner->isContactRestricted);

        $this->liftRestriction($owner);
    }

    public function testUnblockLiftsRestriction(): void
    {
        $client = $this->login(self::ADMIN);

        $thread = $this->createThread();
        $owner = $thread->owner;
        $owner->contactRestrictedPermanently = true;
        $this->flush();

        $token = $this->csrfTokenFromPage($client, $this->actionUrl($client, $thread, 'unblock'), 'input[name="_token"]', 'value');

        $client->request(
            Request::METHOD_POST,
            $this->actionUrl($client, $thread, 'unblock'),
            [
                '_token' => $token,
            ],
        );

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser(self::OWNER);
        self::assertFalse($reloaded->contactRestrictedPermanently);
        self::assertFalse($reloaded->isContactRestricted);
    }

    public function testComposeToSingleUserCreatesThread(): void
    {
        $client = $this->login(self::ADMIN);
        $recipient = $this->createTestUser('compose-single@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_thread_compose_form[recipientId]' => (string) $recipient->id,
            'contact_thread_compose_form[category]' => 'bug',
            'contact_thread_compose_form[subject]' => 'Sujet direct',
            'contact_thread_compose_form[body]' => 'Message envoyé directement à un utilisateur.',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        $threads = $this->findThreadsForOwner($recipient);
        self::assertCount(1, $threads);
        self::assertSame('Sujet direct', $threads[0]->subject);

        $firstMessage = $threads[0]->messages->first();
        self::assertInstanceOf(ContactThreadMessage::class, $firstMessage);
        self::assertTrue($firstMessage->fromAdmin);
        self::assertSame(ContactThreadStatusEnum::ANSWERED_BY_ADMIN, $threads[0]->status);

        $this->deleteTestUser('compose-single@test.com');
    }

    public function testComposeToSingleUserSanitizesHtmlBodyAndAcceptsImage(): void
    {
        $client = $this->login(self::ADMIN);
        $recipient = $this->createTestUser('compose-html@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_thread_compose_form[recipientId]' => (string) $recipient->id,
            'contact_thread_compose_form[category]' => 'bug',
            'contact_thread_compose_form[subject]' => 'Sujet enrichi',
            'contact_thread_compose_form[body]' => '<p>Bonjour</p><script>alert(1)</script>',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), [
            'image' => ImageTestHelper::createFakeImage('compose.jpg', 'image/jpeg'),
        ]);

        self::assertResponseRedirects();

        $threads = $this->findThreadsForOwner($recipient);
        self::assertCount(1, $threads);

        $firstMessage = $threads[0]->messages->first();
        self::assertInstanceOf(ContactThreadMessage::class, $firstMessage);
        self::assertStringContainsString('<p>Bonjour</p>', $firstMessage->body);
        self::assertStringNotContainsString('<script', $firstMessage->body);
        self::assertNotNull($firstMessage->imagePath);

        $this->deleteTestUser('compose-html@test.com');
    }

    public function testComposeWithUnknownRecipientIdFailsValidation(): void
    {
        $client = $this->login(self::ADMIN);

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_thread_compose_form[recipientId]' => '0199999999999999999999999999',
            'contact_thread_compose_form[category]' => 'bug',
            'contact_thread_compose_form[subject]' => 'Sujet invalide',
            'contact_thread_compose_form[body]' => 'Ce message ne devrait jamais être envoyé.',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Aucun utilisateur valide trouvé');
    }

    private function composeUrl(): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(ContactThreadCrudController::class)
            ->setAction('compose')
            ->generateUrl()
        ;
    }

    /**
     * @return list<ContactThread>
     */
    private function findThreadsForOwner(User $owner): array
    {
        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);

        /** @var list<ContactThread> */
        return $contactThreadRepository->findByOwnerOrderedByActivity($owner)->getQuery()->getResult();
    }

    private function createTestUser(string $email, string $locale): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'T' . substr(bin2hex(random_bytes(8)), 0, 16);
        $user->lastLogin = new \DateTimeImmutable();
        $user->locale = $locale;

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function deleteTestUser(string $email): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneByEmail($email);

        if (! $user instanceof User) {
            return;
        }

        $entityManager->remove($user);
        $entityManager->flush();
    }

    private function actionUrl(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, ContactThread $thread, string $action): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(ContactThreadCrudController::class)
            ->setAction($action)
            ->setEntityId($thread->id)
            ->generateUrl()
        ;
    }

    private function createThread(string $subject = 'Sujet de test'): ContactThread
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return ContactThreadTestHelper::createThread($entityManager, $this->reloadUser(self::OWNER), $subject);
    }

    private function reloadThread(ContactThread $thread): ContactThread
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var ContactThread $reloaded */
        $reloaded = $entityManager->getRepository(ContactThread::class)->find($thread->id);

        return $reloaded;
    }

    private function reloadUser(string $email): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var User $user */
        $user = $userRepository->findOneByEmail($email);

        return $user;
    }

    private function liftRestriction(User $user): void
    {
        $user->contactRestrictedPermanently = false;
        $user->contactRestrictedUntil = null;
        $user->contactRestrictionDuration = null;
        $this->flush();
    }

    private function flush(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
    }
}
