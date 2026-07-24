<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContactThreadListControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-2@test.com';

    private const string OTHER_USER = 'user-fixture-3@test.com';

    private const string URL = '/fr/messagerie';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }

    public function testShowsEmptyStateWhenNoThreads(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Bienvenue dans ta messagerie');
    }

    public function testShowsOnlyOwnThreads(): void
    {
        $client = $this->login(self::USER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);
        $other = $userRepository->findOneBy([
            'email' => self::OTHER_USER,
        ]);

        if (! $user instanceof User || ! $other instanceof User) {
            throw new \LogicException('Fixture users not found.');
        }

        ContactThreadTestHelper::createThread($entityManager, $user, 'Mon fil à moi');
        ContactThreadTestHelper::createThread($entityManager, $other, "Le fil d'un autre utilisateur");

        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Mon fil à moi', $crawler->filter('body')->text());
        self::assertStringNotContainsString("Le fil d'un autre utilisateur", $crawler->filter('body')->text());
    }

    public function testExcerptStripsAdminHtmlTagsInsteadOfShowingThemLiterally(): void
    {
        $client = $this->login(self::USER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);
        $admin = $userRepository->findOneBy([
            'email' => UserFixtures::USER_ADMIN,
        ]);

        if (! $user instanceof User || ! $admin instanceof User) {
            throw new \LogicException('Fixture users not found.');
        }

        $thread = ContactThreadTestHelper::createThread($entityManager, $user, 'Fil avec réponse enrichie');
        ContactThreadTestHelper::addAdminMessage($entityManager, $thread, $admin, body: '<p>Réponse <strong>formatée</strong></p>');

        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $bodyText = $crawler->filter('body')->text();
        self::assertStringContainsString('Réponse formatée', $bodyText);
        self::assertStringNotContainsString('<p>', $bodyText);
        self::assertStringNotContainsString('<strong>', $bodyText);
    }
}
