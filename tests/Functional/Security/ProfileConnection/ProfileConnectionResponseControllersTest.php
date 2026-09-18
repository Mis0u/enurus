<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Accepter, refuser, annuler et révoquer. Pour disposer d'un jeton CSRF valide de chaque action dans
 * la session de l'acteur, sa page « Mes connexions » est peuplée d'une demande reçue, d'une demande
 * envoyée et d'une connexion acceptée ; les cas de refus par le Voter visent ensuite une AUTRE
 * connexion, pour que seul le Voter — et non un jeton invalide — puisse expliquer le 403.
 */
final class ProfileConnectionResponseControllersTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    public function testAddresseeAcceptsAPendingRequest(): void
    {
        [$client, $received] = $this->loginWithAReceivedRequest();
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/accepter');

        $client->request('POST', $this->actionUrl($received, 'accepter'), [
            '_token' => $token,
        ]);
        $client->followRedirect();

        self::assertSame(ProfileConnectionStatusEnum::ACCEPTED, $this->findConnection($received)?->status);
        self::assertSelectorTextContains('body', 'Connexion établie avec ' . $received->requester->nickname);
    }

    public function testAddresseeDeclinesAPendingRequest(): void
    {
        [$client, $received] = $this->loginWithAReceivedRequest();
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/refuser');

        $client->request('POST', $this->actionUrl($received, 'refuser'), [
            '_token' => $token,
        ]);
        $client->followRedirect();

        self::assertSame(ProfileConnectionStatusEnum::DECLINED, $this->findConnection($received)?->status);
        self::assertSelectorTextContains('body', 'Demande de ' . $received->requester->nickname . ' refusée');
    }

    public function testRequesterCancelsTheirOwnPendingRequest(): void
    {
        $client = $this->login(self::ACTOR);
        $sent = $this->createConnection($this->getUserByEmail(self::ACTOR), $this->getUserByEmail(self::THIRD));
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/annuler');

        $client->request('POST', $this->actionUrl($sent, 'annuler'), [
            '_token' => $token,
        ]);
        $client->followRedirect();

        self::assertNull($this->findConnection($sent));
        self::assertSelectorTextContains('body', 'Demande à ' . $sent->addressee->nickname . ' annulée');
    }

    public function testRequesterCanRevokeAnAcceptedConnection(): void
    {
        $client = $this->login(self::ACTOR);
        $accepted = $this->createConnection($this->getUserByEmail(self::ACTOR), $this->getUserByEmail(self::THIRD), ProfileConnectionStatusEnum::ACCEPTED);
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/retirer');

        $client->request('POST', $this->actionUrl($accepted, 'retirer'), [
            '_token' => $token,
        ]);
        $client->followRedirect();

        self::assertSame(ProfileConnectionStatusEnum::REVOKED, $this->findConnection($accepted)?->status);
        self::assertSelectorTextContains('body', 'Connexion avec ' . $accepted->addressee->nickname . ' retirée');
    }

    public function testAddresseeCanRevokeAnAcceptedConnectionToo(): void
    {
        $client = $this->login(self::ACTOR);
        $accepted = $this->createConnection($this->getUserByEmail(self::THIRD), $this->getUserByEmail(self::ACTOR), ProfileConnectionStatusEnum::ACCEPTED);
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/retirer');

        $client->request('POST', $this->actionUrl($accepted, 'retirer'), [
            '_token' => $token,
        ]);
        $client->followRedirect();

        self::assertSame(ProfileConnectionStatusEnum::REVOKED, $this->findConnection($accepted)?->status);
        self::assertSelectorTextContains('body', 'Connexion avec ' . $accepted->requester->nickname . ' retirée');
    }

    public function testRequesterCannotAcceptTheirOwnRequest(): void
    {
        [$client, , $sent] = $this->loginWithEveryKindOfConnection();
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/accepter');

        $client->request('POST', $this->actionUrl($sent, 'accepter'), [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $this->findConnection($sent)?->status);
    }

    public function testAddresseeCannotCancelARequestTheyReceived(): void
    {
        [$client, $received] = $this->loginWithEveryKindOfConnection();
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/annuler');

        $client->request('POST', $this->actionUrl($received, 'annuler'), [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->findConnection($received));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function actions(): iterable
    {
        yield 'accept' => ['accepter'];
        yield 'decline' => ['refuser'];
        yield 'cancel' => ['annuler'];
        yield 'revoke' => ['retirer'];
    }

    #[DataProvider('actions')]
    public function testAStrangerCannotActOnSomeoneElsesConnection(string $action): void
    {
        [$client] = $this->loginWithEveryKindOfConnection();
        $foreign = $this->createConnection($this->getUserByEmail(self::THIRD), $this->getUserByEmail(self::OTHER));
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/' . $action);

        $client->request('POST', $this->actionUrl($foreign, $action), [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $this->findConnection($foreign)?->status);
    }

    #[DataProvider('actions')]
    public function testAnInvalidCsrfTokenIsRefused(string $action): void
    {
        [$client, $received] = $this->loginWithEveryKindOfConnection();

        $client->request('POST', $this->actionUrl($received, $action), [
            '_token' => 'not-a-valid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $this->findConnection($received)?->status);
    }

    public function testAnAlreadyHandledRequestGivesAFeedbackInsteadOfAnError(): void
    {
        [$client] = $this->loginWithEveryKindOfConnection();
        $alreadyAccepted = $this->createConnection($this->getUserByEmail(self::FOURTH), $this->getUserByEmail(self::ACTOR), ProfileConnectionStatusEnum::ACCEPTED);
        $token = $this->tokenOfForm($client->request('GET', self::LIST_URL), '/accepter');

        $client->request('POST', $this->actionUrl($alreadyAccepted, 'accepter'), [
            '_token' => $token,
        ]);
        $client->followRedirect();

        self::assertSelectorTextContains('body', "Cette action n'est plus possible");
    }

    #[DataProvider('actions')]
    public function testAnonymousVisitorIsRedirectedToLogin(string $action): void
    {
        $client = static::createClient();
        $connection = $this->createConnection($this->getUserByEmail(self::THIRD), $this->getUserByEmail(self::ACTOR));

        $client->request('POST', $this->actionUrl($connection, $action), []);

        self::assertResponseRedirects('/fr/');
    }

    /**
     * @return array{KernelBrowser, ProfileConnection}
     */
    private function loginWithAReceivedRequest(): array
    {
        $client = $this->login(self::ACTOR);
        $received = $this->createConnection($this->getUserByEmail(self::THIRD), $this->getUserByEmail(self::ACTOR));

        return [$client, $received];
    }

    /**
     * @return array{KernelBrowser, ProfileConnection, ProfileConnection, ProfileConnection}
     */
    private function loginWithEveryKindOfConnection(): array
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);

        return [
            $client,
            $this->createConnection($this->getUserByEmail(self::THIRD), $actor),
            $this->createConnection($actor, $this->getUserByEmail(self::FOURTH)),
            $this->createConnection($actor, $this->getUserByEmail(self::OTHER), ProfileConnectionStatusEnum::ACCEPTED),
        ];
    }

    private function actionUrl(ProfileConnection $connection, string $action): string
    {
        return \sprintf('/fr/connexions/%s/%s', $connection->id?->toRfc4122(), $action);
    }
}
