<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileConnectionRequestControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    private const string TARGET_CODE = 'K2M3N4';

    private const string REQUEST_URL = '/fr/connexions/demander';

    public function testValidAliasAndCodeSendsAPendingRequest(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $target = $this->getUserByEmail(self::OTHER);
        $this->makeDiscoverable($target, self::TARGET_CODE);

        $this->submitQuery($client, $target->nickname . '#' . self::TARGET_CODE);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Demande envoyée à ' . $target->nickname);
        $connection = $this->findBetween($actor, $target);
        self::assertNotNull($connection);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $connection->status);
        self::assertSame($actor->id?->toRfc4122(), $connection->requester->id?->toRfc4122());
    }

    public function testCodeIsAcceptedInLowercaseAndAliasIgnoringCase(): void
    {
        $client = $this->login(self::ACTOR);
        $target = $this->getUserByEmail(self::OTHER);
        $this->makeDiscoverable($target, self::TARGET_CODE);

        $this->submitQuery($client, strtoupper($target->nickname) . '#' . strtolower(self::TARGET_CODE));
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Demande envoyée à ' . $target->nickname);
    }

    public function testUnknownCodeGivesTheGenericNotFoundMessageAndCreatesNothing(): void
    {
        $client = $this->login(self::ACTOR);
        $target = $this->getUserByEmail(self::OTHER);

        $this->submitQuery($client, $target->nickname . '#Z9Y8X7');
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Aucun profil trouvé');
        self::assertNull($this->findBetween($this->getUserByEmail(self::ACTOR), $this->getUserByEmail(self::OTHER)));
    }

    public function testProfileThatDidNotOptInIsIndistinguishableFromAnUnknownOne(): void
    {
        $client = $this->login(self::ACTOR);
        $target = $this->getUserByEmail(self::OTHER);
        $target->shareCode = self::TARGET_CODE;
        $target->isDiscoverable = false;
        $this->entityManager()->flush();

        $this->submitQuery($client, $target->nickname . '#' . self::TARGET_CODE);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Aucun profil trouvé');
        self::assertNull($this->findBetween($this->getUserByEmail(self::ACTOR), $this->getUserByEmail(self::OTHER)));
    }

    public function testRightCodeWithTheWrongAliasFindsNobody(): void
    {
        $client = $this->login(self::ACTOR);
        $this->makeDiscoverable($this->getUserByEmail(self::OTHER), self::TARGET_CODE);

        $this->submitQuery($client, 'SomeoneElse#' . self::TARGET_CODE);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Aucun profil trouvé');
    }

    public function testSearchingOneselfFindsNobody(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->makeDiscoverable($actor, self::TARGET_CODE);

        $this->submitQuery($client, $actor->nickname . '#' . self::TARGET_CODE);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Aucun profil trouvé');
    }

    public function testASecondRequestToTheSamePersonIsRefused(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $target = $this->getUserByEmail(self::OTHER);
        $this->makeDiscoverable($target, self::TARGET_CODE);
        $this->createConnection($actor, $target);

        $this->submitQuery($client, $target->nickname . '#' . self::TARGET_CODE);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Une demande est déjà en attente');
    }

    public function testEmptyInputIsRejectedAsInvalid(): void
    {
        $client = $this->login(self::ACTOR);

        $this->submitQuery($client, '');
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Saisie invalide');
    }

    public function testInvalidCsrfTokenCreatesNothing(): void
    {
        $client = $this->login(self::ACTOR);
        $target = $this->getUserByEmail(self::OTHER);
        $this->makeDiscoverable($target, self::TARGET_CODE);

        $client->request('POST', self::REQUEST_URL, [
            'profile_connection_request' => [
                'query' => $target->nickname . '#' . self::TARGET_CODE,
                '_token' => 'not-a-valid-token',
            ],
        ]);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Saisie invalide');
        self::assertNull($this->findBetween($this->getUserByEmail(self::ACTOR), $this->getUserByEmail(self::OTHER)));
    }

    public function testSearchesBeyondTheLimitAreRefusedWithARetryMessage(): void
    {
        $client = $this->login(self::ACTOR);
        // Sans cela le noyau redémarre entre les requêtes et le stockage en mémoire du limiter est remis à zéro.
        $client->disableReboot();
        $token = $client->request('GET', self::LIST_URL)->filter('input[name="profile_connection_request[_token]"]')->attr('value') ?? '';
        $searchLimit = 10;

        for ($search = 0; $searchLimit > $search; ++$search) {
            $this->postQuery($client, $token, 'Nobody#Z9Y8X7');
        }
        $this->postQuery($client, $token, 'Nobody#Z9Y8X7');
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Trop de tentatives');
    }

    public function testAnonymousVisitorCannotSendARequest(): void
    {
        $client = static::createClient();

        $client->request('POST', self::REQUEST_URL, []);

        self::assertResponseRedirects('/fr/');
    }

    public function testRequestRouteAnswersNotFoundToAGetRequest(): void
    {
        $client = $this->login(self::ACTOR);

        // La route de repli (NotFoundController) capte tout ce qui ne correspond pas à une route
        // réelle, y compris une méthode non autorisée : 404, pas 405.
        $client->request('GET', self::REQUEST_URL);

        self::assertResponseStatusCodeSame(404);
    }

    private function submitQuery(KernelBrowser $client, string $query): void
    {
        $crawler = $client->request('GET', self::LIST_URL);
        $form = $crawler->filter('form[action$="/connexions/demander"]')->form([
            'profile_connection_request[query]' => $query,
        ]);

        $client->submit($form);
    }

    private function postQuery(KernelBrowser $client, string $token, string $query): void
    {
        $client->request('POST', self::REQUEST_URL, [
            'profile_connection_request' => [
                'query' => $query,
                '_token' => $token,
            ],
        ]);
    }
}
