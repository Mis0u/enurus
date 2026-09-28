<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SettingsDashboardWidgetOrderControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-26-workout@test.com';

    private const string URL = '/fr/reglages/widgets/ordre';

    /**
     * Seuls les widgets débloqués sont listés en réglages : ceux qui manquent sont gardés à la fin,
     * dans l'ordre par défaut — c'est là qu'ils apparaîtront une fois débloqués.
     */
    public function testSavingAnOrderKeepsTheMissingWidgetsAtTheEnd(): void
    {
        $client = $this->login(self::USER);

        $this->sendOrder($client, ['badges', 'session', 'tonnage'], $this->csrfTokenFor($client));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['badges', 'session', 'tonnage', 'muscle_distribution', 'regularity', 'regularity_goal', 'goals', 'heatmap', 'connections'],
            $this->savedWidgetOrder(),
        );
    }

    public function testSettingsListTheWidgetsInTheSavedOrder(): void
    {
        $client = $this->login(self::USER);
        $this->sendOrder($client, ['badges', 'session'], $this->csrfTokenFor($client));

        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $listedWidgets = $crawler->filter('[data-settings--dashboard-widgets-target="row"]')->each(
            static fn ($row): ?string => $row->attr('data-widget'),
        );

        self::assertSame(['badges', 'session'], \array_slice($listedWidgets, 0, 2));
    }

    public function testUnknownWidgetIsRejected(): void
    {
        $client = $this->login(self::USER);

        $this->sendOrder($client, ['badges', 'not_a_real_widget'], $this->csrfTokenFor($client));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testOrderMustBeAListOfWidgetKeys(): void
    {
        $client = $this->login(self::USER);

        $this->sendOrder($client, 'badges', $this->csrfTokenFor($client));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER);

        $this->sendOrder($client, ['badges'], 'invalid');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function sendOrder(KernelBrowser $client, mixed $order, string $csrfToken): void
    {
        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'order' => $order,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );
    }

    private function csrfTokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            '/fr/reglages',
            '[data-settings--dashboard-widgets-csrf-token-value]',
            'data-settings--dashboard-widgets-csrf-token-value',
        );
    }

    /**
     * @return array<string>
     */
    private function savedWidgetOrder(): array
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);
        self::assertNotNull($user);

        return $user->widgetOrder;
    }
}
