<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContactRecipientAutocompleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testSearchReturnsMatchingUsersWithLocale(): void
    {
        $client = $this->login(self::ADMIN);
        $this->createTestUser('autocomplete-match@test.com', 'it');
        $this->createTestUser('autocomplete-nomatch@test.com', 'fr');

        $client->request(Request::METHOD_GET, '/admin/contact-recipients/search?query=autocomplete-match');

        self::assertResponseIsSuccessful();

        /** @var list<array{id: string, email: string, locale: string}> $results */
        $results = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertCount(1, $results);
        self::assertSame('autocomplete-match@test.com', $results[0]['email']);
        self::assertSame('it', $results[0]['locale']);

        $this->deleteTestUser('autocomplete-match@test.com');
        $this->deleteTestUser('autocomplete-nomatch@test.com');
    }

    public function testSearchExcludesTheAdminItself(): void
    {
        $client = $this->login(self::ADMIN);

        $client->request(Request::METHOD_GET, '/admin/contact-recipients/search?query=admin-fixture');

        self::assertResponseIsSuccessful();

        /** @var list<array{id: string, email: string, locale: string}> $results */
        $results = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([], $results);
    }

    public function testSearchWithBlankQueryReturnsEmptyList(): void
    {
        $client = $this->login(self::ADMIN);

        $client->request(Request::METHOD_GET, '/admin/contact-recipients/search?query=');

        self::assertResponseIsSuccessful();
        self::assertSame('[]', $client->getResponse()->getContent());
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
}
