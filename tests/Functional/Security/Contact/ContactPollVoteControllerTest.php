<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\DataFixtures\UserFixtures;
use App\Entity\ContactBroadcast;
use App\Entity\ContactPollOption;
use App\Entity\ContactPollVote;
use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactCategoryEnum;
use App\Repository\ContactThreadRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class ContactPollVoteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = 'user-fixture-2@test.com';

    public function testOwnerCanVoteOnce(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        [$thread, $options] = $this->createVoteThread($entityManager, $owner, $admin, closesAt: '+7 days');

        $url = \sprintf('/fr/messagerie/%s', $thread->id);
        $crawler = $client->request(Request::METHOD_GET, $url);
        $token = $this->getCsrfToken($crawler);

        // Régression : vérifie que le thème de formulaire custom (radios stylées) est bien
        // appliqué plutôt que le rendu Symfony par défaut (peu lisible dans le thème sombre).
        self::assertGreaterThan(0, $crawler->filter('input[name="contact_vote_form[option]"].peer')->count());

        $client->request(Request::METHOD_POST, \sprintf('%s/voter', $url), [
            'contact_vote_form' => [
                'option' => (string) $options[0]->id,
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects($url);

        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertSelectorTextContains('body', 'Turc');

        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        $reloadedThread = $contactThreadRepository->find($thread->id);

        self::assertNotNull($reloadedThread);
        self::assertNotNull($reloadedThread->pollVote);
        self::assertSame((string) $options[0]->id, (string) $reloadedThread->pollVote->option->id);
    }

    public function testOwnerCannotVoteTwice(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        [$thread, $options] = $this->createVoteThread($entityManager, $owner, $admin, closesAt: '+7 days');

        $vote = new ContactPollVote();
        $vote->thread = $thread;
        $vote->option = $options[0];
        $thread->pollVote = $vote;
        $entityManager->persist($vote);
        $entityManager->flush();

        $client->request(Request::METHOD_POST, \sprintf('/fr/messagerie/%s/voter', $thread->id), [
            'contact_vote_form' => [
                'option' => (string) $options[1]->id,
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerCannotVoteAfterPollClosed(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        [$thread, $options] = $this->createVoteThread($entityManager, $owner, $admin, closesAt: '-1 day');

        $client->request(Request::METHOD_POST, \sprintf('/fr/messagerie/%s/voter', $thread->id), [
            'contact_vote_form' => [
                'option' => (string) $options[0]->id,
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPollShowsDaysRemainingBeforeVoting(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        [$thread] = $this->createVoteThread($entityManager, $owner, $admin, closesAt: '+3 days');

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        self::assertStringContainsString('Se termine dans 3 jours', $crawler->filter('body')->text());
    }

    public function testPollShowsDaysRemainingAfterVoting(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        [$thread, $options] = $this->createVoteThread($entityManager, $owner, $admin, closesAt: '+2 days');

        $vote = new ContactPollVote();
        $vote->thread = $thread;
        $vote->option = $options[0];
        $thread->pollVote = $vote;
        $entityManager->persist($vote);
        $entityManager->flush();

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/messagerie/%s', $thread->id));

        self::assertStringContainsString('Se termine dans 2 jours', $crawler->filter('body')->text());
    }

    public function testReplyIsForbiddenOnVoteThread(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);
        $admin = $this->getUserByEmail(UserFixtures::USER_ADMIN);

        [$thread] = $this->createVoteThread($entityManager, $owner, $admin, closesAt: '+7 days');

        $client->request(Request::METHOD_POST, \sprintf('/fr/messagerie/%s/repondre', $thread->id), [
            'contact_reply_form' => [
                'message' => 'Ce message ne devrait jamais être accepté sur un sondage.',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{0: ContactThread, 1: list<ContactPollOption>}
     */
    private function createVoteThread(EntityManagerInterface $entityManager, User $owner, User $admin, string $closesAt): array
    {
        $broadcast = new ContactBroadcast();
        $broadcast->sentBy = $admin;
        $broadcast->category = ContactCategoryEnum::VOTE;
        $broadcast->subject = 'Prochaine langue ?';
        $broadcast->body = 'Quelle langue traduire en premier ?';
        $broadcast->target = ContactBroadcastTargetEnum::ALL;
        $broadcast->recipientCount = 1;
        $broadcast->pollClosesAt = new \DateTimeImmutable($closesAt);

        $labels = ['Turc', 'Norvégien'];
        $options = [];
        foreach ($labels as $position => $label) {
            $option = new ContactPollOption();
            $option->label = $label;
            $option->position = $position;
            $broadcast->addPollOption($option);
            $options[] = $option;
        }

        $entityManager->persist($broadcast);

        $thread = ContactThreadTestHelper::createThread(
            $entityManager,
            $owner,
            subject: $broadcast->subject,
            category: ContactCategoryEnum::VOTE,
            broadcast: $broadcast,
        );

        return [$thread, $options];
    }

    private function getCsrfToken(Crawler $crawler): string
    {
        return (string) $crawler->filter('input[name="contact_vote_form[_token]"]')->attr('value');
    }
}
