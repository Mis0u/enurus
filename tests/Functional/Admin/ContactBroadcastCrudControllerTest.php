<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\ContactBroadcastCrudController;
use App\Controller\Admin\ContactThreadCrudController;
use App\Entity\ContactBroadcast;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Exception\Translation\TranslationFailedException;
use App\Message\SendContactBroadcastMessage;
use App\MessageHandler\SendContactBroadcastMessageHandler;
use App\Repository\ContactBroadcastRepository;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class ContactBroadcastCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use MailerAssertionsTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testComposeToLocaleListsSingleGroupedRowWithTargetDescription(): void
    {
        $client = $this->login(self::ADMIN);
        $italian = $this->createTestUser('broadcast-index-it@test.com', 'it');
        $french = $this->createTestUser('broadcast-index-fr@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'it',
            'contact_broadcast_compose_form[subject]' => 'Annonce IT',
            'contact_broadcast_compose_form[body]' => 'Message informatif pour les utilisateurs italiens.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $this->processPendingBroadcast('Annonce IT');

        self::assertCount(1, $this->findThreadsForOwner($italian));
        self::assertCount(0, $this->findThreadsForOwner($french));

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(ContactBroadcastCrudController::class)->setAction('index')->generateUrl();

        $client->request(Request::METHOD_GET, $indexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Annonce IT');
        self::assertSelectorTextContains('body', 'Tous les utilisateurs (IT)');

        $this->deleteTestUser('broadcast-index-it@test.com');
        $this->deleteTestUser('broadcast-index-fr@test.com');
    }

    public function testComposeToAllExcludesPendingDeletionAccount(): void
    {
        $client = $this->login(self::ADMIN);
        $active = $this->createTestUser('broadcast-all-active@test.com', 'fr');
        $pendingDeletion = $this->createTestUser('broadcast-all-pending@test.com', 'fr');
        $pendingDeletion->deletionRequestedAt = new \DateTimeImmutable();
        $this->flush();

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce globale',
            'contact_broadcast_compose_form[body]' => 'Message informatif pour tout le monde.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $this->processPendingBroadcast('Annonce globale');

        self::assertCount(1, $this->findThreadsForOwner($active));
        self::assertCount(0, $this->findThreadsForOwner($pendingDeletion));

        $this->deleteTestUser('broadcast-all-active@test.com');
        $this->deleteTestUser('broadcast-all-pending@test.com');
    }

    public function testComposeForcesInformativeCategoryAndHidesThreadFromMessagerieIndex(): void
    {
        $client = $this->login(self::ADMIN);
        $recipient = $this->createTestUser('broadcast-category@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce forcée informative',
            'contact_broadcast_compose_form[body]' => 'Peu importe, toujours informatif.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $this->processPendingBroadcast('Annonce forcée informative');

        $threads = $this->findThreadsForOwner($recipient);
        self::assertCount(1, $threads);
        self::assertSame(ContactCategoryEnum::INFORMATIVE, $threads[0]->category);
        self::assertNotNull($threads[0]->broadcast);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $threadIndexUrl = $adminUrlGenerator->setController(ContactThreadCrudController::class)->setAction('index')->generateUrl();
        $threadDetailUrl = $adminUrlGenerator
            ->setController(ContactThreadCrudController::class)
            ->setAction('detail')
            ->setEntityId($threads[0]->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $threadIndexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="' . $threadDetailUrl . '"]');

        $this->deleteTestUser('broadcast-category@test.com');
    }

    public function testComposeSanitizesHtmlBodyStrippingScriptTags(): void
    {
        $client = $this->login(self::ADMIN);
        $this->createTestUser('broadcast-html@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce enrichie',
            'contact_broadcast_compose_form[body]' => '<p>Info <strong>importante</strong></p><script>alert(1)</script>',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $broadcast = $this->findBroadcastBySubject('Annonce enrichie');
        self::assertStringContainsString('<strong>importante</strong>', $broadcast->body);
        self::assertStringNotContainsString('<script', $broadcast->body);

        $this->deleteTestUser('broadcast-html@test.com');
    }

    public function testComposeReturnsImmediatelyWithoutCreatingRecipientThreadsSynchronously(): void
    {
        $client = $this->login(self::ADMIN);
        $recipient = $this->createTestUser('broadcast-async@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce asynchrone',
            'contact_broadcast_compose_form[body]' => 'Ce message est traité en tâche de fond.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $broadcast = $this->findBroadcastBySubject('Annonce asynchrone');
        self::assertGreaterThan(0, $broadcast->recipientCount);
        self::assertCount(0, $this->findThreadsForOwner($recipient));

        $this->processPendingBroadcast('Annonce asynchrone');
        self::assertCount(1, $this->findThreadsForOwner($recipient));

        $this->deleteTestUser('broadcast-async@test.com');
    }

    public function testComposeWithImageCopiesADistinctFilePerRecipient(): void
    {
        $client = $this->login(self::ADMIN);
        $first = $this->createTestUser('broadcast-image-1@test.com', 'fr');
        $second = $this->createTestUser('broadcast-image-2@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce avec image',
            'contact_broadcast_compose_form[body]' => 'Voici une image jointe.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), [
            'image' => ImageTestHelper::createFakeImage('broadcast.jpg', 'image/jpeg'),
        ]);
        self::assertResponseRedirects();

        $this->processPendingBroadcast('Annonce avec image');

        $firstMessage = $this->findThreadsForOwner($first)[0]->messages->first();
        $secondMessage = $this->findThreadsForOwner($second)[0]->messages->first();

        self::assertInstanceOf(ContactThreadMessage::class, $firstMessage);
        self::assertInstanceOf(ContactThreadMessage::class, $secondMessage);
        self::assertNotNull($firstMessage->imagePath);
        self::assertNotNull($secondMessage->imagePath);
        self::assertNotSame($firstMessage->imagePath, $secondMessage->imagePath);

        $this->deleteTestUser('broadcast-image-1@test.com');
        $this->deleteTestUser('broadcast-image-2@test.com');
    }

    public function testDetailShowsSubjectBodyAndTargetForAllUsers(): void
    {
        $client = $this->login(self::ADMIN);
        $this->createTestUser('broadcast-detail-all@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce globale detail',
            'contact_broadcast_compose_form[body]' => 'Corps du message groupé.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $broadcast = $this->findBroadcastBySubject('Annonce globale detail');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $detailUrl = $adminUrlGenerator
            ->setController(ContactBroadcastCrudController::class)
            ->setAction('detail')
            ->setEntityId($broadcast->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $detailUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Annonce globale detail');
        self::assertSelectorTextContains('body', 'Corps du message groupé.');
        self::assertSelectorTextContains('body', 'Tous les utilisateurs');

        $this->deleteTestUser('broadcast-detail-all@test.com');
    }

    public function testDeletingBroadcastCascadeDeletesItsRecipientThreads(): void
    {
        $client = $this->login(self::ADMIN);
        $recipient = $this->createTestUser('broadcast-delete-cascade@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce à supprimer',
            'contact_broadcast_compose_form[body]' => 'Ce message sera supprimé avec la diffusion.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $broadcast = $this->processPendingBroadcast('Annonce à supprimer');
        self::assertCount(1, $this->findThreadsForOwner($recipient));

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $detailUrl = $adminUrlGenerator
            ->setController(ContactBroadcastCrudController::class)
            ->setAction('detail')
            ->setEntityId($broadcast->id)
            ->generateUrl()
        ;
        $deleteUrl = $adminUrlGenerator
            ->setController(ContactBroadcastCrudController::class)
            ->setAction('delete')
            ->setEntityId($broadcast->id)
            ->generateUrl()
        ;

        $token = $this->csrfTokenFromPage($client, $detailUrl, 'input[name="token"]', 'value');

        $client->request(Request::METHOD_POST, $deleteUrl, [
            'token' => $token,
        ]);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        self::assertNull($entityManager->getRepository(ContactBroadcast::class)->find($broadcast->id));

        self::assertCount(0, $this->findThreadsForOwner($recipient));

        $this->deleteTestUser('broadcast-delete-cascade@test.com');
    }

    public function testComposeVoteCreatesPollOptionsAndClosesAtFromDuration(): void
    {
        $client = $this->login(self::ADMIN);
        $recipient = $this->createTestUser('broadcast-vote-create@test.com', 'fr');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[category]' => 'vote',
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Prochaine langue ?',
            'contact_broadcast_compose_form[body]' => 'Quelle langue traduire en premier ?',
            'contact_broadcast_compose_form[pollOptions]' => json_encode(['Turc', 'Norvégien', 'Suédois'], JSON_THROW_ON_ERROR),
            'contact_broadcast_compose_form[pollDurationDays]' => '7',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $broadcast = $this->processPendingBroadcast('Prochaine langue ?');

        self::assertTrue($broadcast->isPoll());
        self::assertCount(3, $broadcast->pollOptions);
        self::assertSame(['Turc', 'Norvégien', 'Suédois'], array_map(
            static fn ($option): string => $option->label,
            $broadcast->pollOptions->toArray(),
        ));
        self::assertNotNull($broadcast->pollClosesAt);
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+7 days'))->getTimestamp(),
            $broadcast->pollClosesAt->getTimestamp(),
            5,
        );

        $threads = $this->findThreadsForOwner($recipient);
        self::assertCount(1, $threads);
        self::assertSame(ContactCategoryEnum::VOTE, $threads[0]->category);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(ContactBroadcastCrudController::class)->setAction('index')->generateUrl();

        $client->request(Request::METHOD_GET, $indexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sondage');
        self::assertSelectorTextNotContains('body', 'Inaccessible');

        // Régression : `u.gender` est une colonne enum-typée (contrairement à `u.locale`, chaîne
        // brute) — le détail affiche les résultats groupés par genre, qui a fait planter
        // ContactPollVoteRepository::countParticipationGroupedByUserProperty() en utilisant un
        // GenderEnum comme clé de tableau.
        $detailUrl = $adminUrlGenerator
            ->setController(ContactBroadcastCrudController::class)
            ->setAction('detail')
            ->setEntityId($broadcast->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $detailUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Résultats du sondage');

        $this->deleteTestUser('broadcast-vote-create@test.com');
    }

    public function testComposeToAllTranslatesOnceForAllRecipientsSharingTheSameLocale(): void
    {
        $client = $this->login(self::ADMIN);
        $germanFirst = $this->createTestUser('broadcast-translate-de-1@test.com', 'de');
        $germanSecond = $this->createTestUser('broadcast-translate-de-2@test.com', 'de');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce multilingue',
            'contact_broadcast_compose_form[body]' => 'Corps du message multilingue.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $callCountByTargetLang = [];
        $this->processPendingBroadcast('Annonce multilingue', static function (array $payload) use (&$callCountByTargetLang): MockResponse {
            $callCountByTargetLang[$payload['target_lang']] = ($callCountByTargetLang[$payload['target_lang']] ?? 0) + 1;

            return new MockResponse(json_encode([
                'translations' => [
                    [
                        'text' => 'Übersetzter Betreff',
                    ],
                    [
                        'text' => 'Übersetzter Inhalt',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        self::assertSame(1, $callCountByTargetLang['DE'] ?? 0, 'DeepL doit être appelé une seule fois par langue, jamais par destinataire.');

        $firstMessage = $this->findThreadsForOwner($germanFirst)[0]->messages->first();
        $secondMessage = $this->findThreadsForOwner($germanSecond)[0]->messages->first();

        self::assertInstanceOf(ContactThreadMessage::class, $firstMessage);
        self::assertInstanceOf(ContactThreadMessage::class, $secondMessage);
        self::assertSame('Übersetzter Inhalt', $firstMessage->body);
        self::assertSame('Übersetzter Inhalt', $secondMessage->body);

        $this->deleteTestUser('broadcast-translate-de-1@test.com');
        $this->deleteTestUser('broadcast-translate-de-2@test.com');
    }

    public function testComposeToAllCreatesNoThreadAndNotifiesAdminWhenATranslationFails(): void
    {
        $client = $this->login(self::ADMIN);
        $frenchRecipient = $this->createTestUser('broadcast-fail-fr@test.com', 'fr');
        $germanRecipient = $this->createTestUser('broadcast-fail-de@test.com', 'de');

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Annonce en échec',
            'contact_broadcast_compose_form[body]' => 'Ce message ne doit jamais partir.',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        self::assertResponseRedirects();

        $broadcast = $this->findBroadcastBySubject('Annonce en échec');

        $this->expectException(TranslationFailedException::class);

        try {
            $this->processPendingBroadcast('Annonce en échec', static fn (): MockResponse => new MockResponse('', [
                'http_code' => 403,
            ]));
        } finally {
            self::assertCount(0, $this->findThreadsForOwner($frenchRecipient));
            self::assertCount(0, $this->findThreadsForOwner($germanRecipient));

            self::assertEmailCount(1);
            $email = self::getMailerMessage();
            self::assertNotNull($email);
            self::assertEmailHtmlBodyContains($email, $broadcast->subject);

            $this->deleteTestUser('broadcast-fail-fr@test.com');
            $this->deleteTestUser('broadcast-fail-de@test.com');
        }
    }

    public function testComposeVoteRequiresAtLeastTwoOptions(): void
    {
        $client = $this->login(self::ADMIN);

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[category]' => 'vote',
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Sondage invalide',
            'contact_broadcast_compose_form[body]' => 'Une seule option, ce qui ne devrait pas suffire.',
            'contact_broadcast_compose_form[pollOptions]' => json_encode(['Seule option'], JSON_THROW_ON_ERROR),
            'contact_broadcast_compose_form[pollDurationDays]' => '7',
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Un sondage doit avoir au moins 2 options.');

        /** @var ContactBroadcastRepository $repository */
        $repository = static::getContainer()->get(ContactBroadcastRepository::class);
        self::assertNull($repository->findOneBy([
            'subject' => 'Sondage invalide',
        ]));
    }

    public function testComposeVoteRequiresDuration(): void
    {
        $client = $this->login(self::ADMIN);

        $client->request(Request::METHOD_GET, $this->composeUrl());
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Envoyer')->form([
            'contact_broadcast_compose_form[category]' => 'vote',
            'contact_broadcast_compose_form[target]' => 'all',
            'contact_broadcast_compose_form[subject]' => 'Sondage sans durée',
            'contact_broadcast_compose_form[body]' => 'Options valides mais durée manquante.',
            'contact_broadcast_compose_form[pollOptions]' => json_encode(['Option A', 'Option B'], JSON_THROW_ON_ERROR),
        ]);
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'La durée du sondage est obligatoire.');

        /** @var ContactBroadcastRepository $repository */
        $repository = static::getContainer()->get(ContactBroadcastRepository::class);
        self::assertNull($repository->findOneBy([
            'subject' => 'Sondage sans durée',
        ]));
    }

    /**
     * La création des fils par destinataire est déportée dans SendContactBroadcastMessageHandler
     * via Messenger (transport `async`, routé vers `in-memory://` en environnement de test — cf.
     * la section "when at test" de config/packages/messenger.yaml). On récupère le message
     * réellement envoyé (avec son éventuel `sourceImagePath` déjà uploadé) puis on invoke le
     * handler directement, comme le ferait le worker, plutôt que de compter sur une vraie
     * consommation de la queue.
     */
    private function processPendingBroadcast(string $subject, ?callable $deeplResponseFactory = null): ContactBroadcast
    {
        // Posé ici (juste avant l'invocation directe du handler) plutôt qu'après login() : le
        // KernelBrowser reboote le kernel — donc reconstruit le container — avant chaque requête
        // HTTP, ce qui effacerait un stub posé plus tôt dans le test.
        $this->stubDeeplTranslation($deeplResponseFactory);

        $broadcast = $this->findBroadcastBySubject($subject);

        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.broadcast_test');

        $envelope = null;

        foreach ($transport->getSent() as $sentEnvelope) {
            $sentMessage = $sentEnvelope->getMessage();

            if ($sentMessage instanceof SendContactBroadcastMessage && $sentMessage->broadcastId === (string) $broadcast->id) {
                $envelope = $sentEnvelope;
            }
        }

        if (null === $envelope) {
            throw new \LogicException(\sprintf('No SendContactBroadcastMessage dispatched for broadcast "%s".', $subject));
        }

        /** @var SendContactBroadcastMessage $message */
        $message = $envelope->getMessage();

        /** @var SendContactBroadcastMessageHandler $handler */
        $handler = static::getContainer()->get(SendContactBroadcastMessageHandler::class);
        $handler($message);

        return $broadcast;
    }

    private function findBroadcastBySubject(string $subject): ContactBroadcast
    {
        /** @var ContactBroadcastRepository $repository */
        $repository = static::getContainer()->get(ContactBroadcastRepository::class);

        $broadcast = $repository->findOneBy([
            'subject' => $subject,
        ]);

        if (! $broadcast instanceof ContactBroadcast) {
            throw new \LogicException(\sprintf('ContactBroadcast with subject "%s" not found.', $subject));
        }

        return $broadcast;
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

    private function composeUrl(): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(ContactBroadcastCrudController::class)
            ->setAction('compose')
            ->generateUrl()
        ;
    }

    /**
     * Les fixtures de base (`UserFixtures::loadLocaleUsers()`) créent un destinataire par locale,
     * toujours présents pour un ciblage "tous les utilisateurs" — remplace le client HTTP DeepL par
     * un double qui renvoie le texte tel quel par défaut, pour ne jamais dépendre d'un vrai appel
     * réseau dans les tests. `$responseFactory`, si fourni, reçoit le payload décodé
     * `{text, source_lang, target_lang}` de chaque requête et permet de simuler un échec ciblé.
     *
     * @param (callable(array{text: list<string>, source_lang: string, target_lang: string}): MockResponse)|null $responseFactory
     */
    private function stubDeeplTranslation(?callable $responseFactory = null): void
    {
        $responseFactory ??= static fn (array $payload): MockResponse => new MockResponse(json_encode([
            'translations' => array_map(
                static fn (string $text): array => [
                    'text' => $text,
                ],
                $payload['text'],
            ),
        ], JSON_THROW_ON_ERROR));

        $mockHttpClient = new MockHttpClient(static function (string $method, string $url, array $options) use ($responseFactory): MockResponse {
            /** @var array{text: list<string>, source_lang: string, target_lang: string} $payload */
            $payload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);

            return $responseFactory($payload);
        });

        static::getContainer()->set('deepl.http_client', $mockHttpClient);
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

    private function flush(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
    }
}
