<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\DataFixtures\UserFixtures;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContactCreateControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-1@test.com';

    private const string URL = '/fr/contact';

    public function testValidSubmissionCreatesThreadAndRedirects(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);
        $token = $this->getCsrfToken($crawler);

        $client->request(Request::METHOD_POST, self::URL, [
            'contact_form' => [
                'category' => 'bug',
                'subject' => 'Un souci sur le tableau de bord',
                'message' => 'Le widget tonnage affiche une valeur incohérente depuis ce matin.',
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects(self::URL);

        $thread = $this->findLastThread();

        self::assertNotNull($thread);
        self::assertSame('Un souci sur le tableau de bord', $thread->subject);
        self::assertCount(1, $thread->messages);

        $message = $thread->messages->first();

        if (! $message instanceof ContactThreadMessage) {
            throw new \LogicException('Expected the thread to have a first message.');
        }

        self::assertFalse($message->fromAdmin);
        self::assertNull($message->imagePath);
    }

    public function testValidSubmissionWithImagePersistsImagePath(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);
        $token = $this->getCsrfToken($crawler);

        $client->request(Request::METHOD_POST, self::URL, [
            'contact_form' => [
                'category' => 'bug',
                'subject' => 'Un souci avec une capture',
                'message' => 'Voici une capture qui montre le bug rencontré sur le tableau de bord.',
                '_token' => $token,
            ],
        ], [
            'image' => ImageTestHelper::createFakeImage('bug.jpg', 'image/jpeg'),
        ]);

        $this->assertResponseRedirects(self::URL);

        $thread = $this->findLastThread();

        self::assertNotNull($thread);

        $message = $thread->messages->first();

        if (! $message instanceof ContactThreadMessage) {
            throw new \LogicException('Expected the thread to have a first message.');
        }

        self::assertNotNull($message->imagePath);
    }

    public function testSubmissionWithInvalidImageTypeIsRejected(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);
        $token = $this->getCsrfToken($crawler);

        $client->request(Request::METHOD_POST, self::URL, [
            'contact_form' => [
                'category' => 'bug',
                'subject' => 'Un souci avec une capture',
                'message' => 'Voici une capture qui montre le bug rencontré sur le tableau de bord.',
                '_token' => $token,
            ],
        ], [
            'image' => ImageTestHelper::createFakeImage('document.pdf', 'application/pdf'),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSubmissionWithoutCategoryIsRejected(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);
        $token = $this->getCsrfToken($crawler);

        $client->request(Request::METHOD_POST, self::URL, [
            'contact_form' => [
                'category' => '',
                'subject' => 'Sujet valide',
                'message' => 'Message suffisamment long pour passer la validation du formulaire.',
                '_token' => $token,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testSubmissionWithTooShortMessageIsRejected(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);
        $token = $this->getCsrfToken($crawler);

        $client->request(Request::METHOD_POST, self::URL, [
            'contact_form' => [
                'category' => 'bug',
                'subject' => 'Sujet valide',
                'message' => 'Court',
                '_token' => $token,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRestrictedUserCannotCreateThreadEvenWithDirectPost(): void
    {
        $client = $this->login(UserFixtures::USER_RESTRICTED_ONE_MONTH);

        $client->request(Request::METHOD_POST, self::URL, [
            'contact_form' => [
                'category' => 'bug',
                'subject' => 'Ne devrait jamais être créé',
                'message' => 'Ce message ne devrait jamais être enregistré car cet utilisateur est restreint.',
                '_token' => 'invalid-or-missing-token-is-fine-restriction-checked-first',
            ],
        ]);

        $this->assertResponseRedirects(self::URL);

        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        self::assertNull($contactThreadRepository->findOneBy([
            'subject' => 'Ne devrait jamais être créé',
        ]));
    }

    public function testSixthAttemptWithinWindowIsRateLimited(): void
    {
        $client = $this->login(self::USER);
        $client->disableReboot();

        $crawler = $client->request(Request::METHOD_GET, self::URL);

        for ($i = 0; 5 > $i; $i++) {
            $form = $crawler->filter('form[name="contact_form"]')->form([
                'contact_form[category]' => 'bug',
                'contact_form[subject]' => \sprintf('Sujet numéro %d', $i),
                'contact_form[message]' => 'Message suffisamment long pour passer la validation du formulaire.',
            ]);

            $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        }

        $form = $crawler->filter('form[name="contact_form"]')->form([
            'contact_form[category]' => 'bug',
            'contact_form[subject]' => 'Sujet numéro 6',
            'contact_form[message]' => 'Message suffisamment long pour passer la validation du formulaire.',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        $this->assertResponseRedirects(self::URL);
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Trop de tentatives');
    }

    private function getCsrfToken(Crawler $crawler): string
    {
        return (string) $crawler->filter('#contact_form__token')->attr('value');
    }

    private function findLastThread(): ?ContactThread
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);

        return $contactThreadRepository->findOneBy([
            'owner' => $user,
        ], [
            'id' => 'DESC',
        ]);
    }
}
