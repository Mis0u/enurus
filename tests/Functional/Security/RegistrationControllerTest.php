<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use function Symfony\Component\Clock\now;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class RegistrationControllerTest extends WebTestCase
{
    private const string PASSWORD = 'pass_PASS?1234';

    /**
     * @return array{array{'male'}, array{'female'}}
     */
    public static function genderProvider(): array
    {
        return [
            ['male'],
            ['female'],
        ];
    }

    public function testRegistrationPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/inscription');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            'title',
            'Inscription',
            'Le titre de la page ne contient pas le texte demandé'
        );
    }

    public function testHoneyPotIsRedirectToLoginPageWithoutPersistInDatabase(): void
    {
        $user = $this->fillField('male', 'bot@test.com', 5, '/fr/', 'Je suis un bot');
        $this->assertNull($user);
    }

    public function testHoneyPotFormSubmitToFastWithoutPersistInDatabase(): void
    {
        $user = $this->fillField('male', 'bot@test.com', 1, '/fr/');
        $this->assertNull($user);
    }

    #[DataProvider('genderProvider')]
    public function testRegistrationSuccess(string $gender): void
    {
        $user = $this->fillField($gender, 'no_bot@test.com', 5, '/fr/dashboard');
        $this->assertNotNull($user);
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getLastLogin());
        $this->assertSame($user->getLastLogin()->format('Y-m-d'), now()->format('Y-m-d'));
        $this->assertSame($user->getGender(), $gender);
        $this->assertSame('fr', $user->getLocale());
    }

    public function testRedirectToDashboardIfUserIsAuthenticated(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var UserInterface $testUser */
        $testUser = $userRepository->findOneBy([
            'email' => 'user-fixture-0@test.com',
        ]);

        $client->loginUser($testUser);

        $client->request(Request::METHOD_GET, '/fr/inscription');
        $this->assertResponseRedirects('/fr/dashboard');
    }

    private function fillField(
        string $gender,
        string $email,
        int $sleepSeconds,
        string $route,
        ?string $honeyPot = null
    ): ?User {
        $client = static::createClient();
        $client->disableReboot(); // CONFLIT AVEC LE CLOCK DONC POUR CE TEST JAI DESACTIVE LE REBOOT
        $mockClock = new MockClock('2026-01-29 08:46:00');
        $client->getContainer()->set(ClockInterface::class, $mockClock); //OBLIGE DE SET SINON IL RECUPERE L'HEURE DU SYSTEME

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get('doctrine');
        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription');

        $this->assertSelectorTextContains('button', 'Créer mon compte', 'Le sélecteur contenant \'Créer mon compte\' est introuvable');
        $buttonCrawlerNode = $crawler->selectButton('Créer mon compte');
        $form = $buttonCrawlerNode->form();

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);

        $mockClock->sleep($sleepSeconds);

        $client->submit($form, [
            'registration_form[gender]' => $gender,
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[website]' => $honeyPot,
        ]);
        $this->assertResponseRedirects($route);
        $client->followRedirect();

        return $userRepository->findOneBy([
            'email' => $email,
        ]);
    }
}
