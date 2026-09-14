<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsDashboardWidgetsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/widgets',
            'fr' => '/reglages/widgets',
            'it' => '/impostazioni/widget',
            'es' => '/ajustes/widgets',
            'pt' => '/definicoes/widgets',
            'de' => '/einstellungen/widgets',
            'nl' => '/instellingen/widgets',
            'pl' => '/ustawienia/widgety',
        ],
        name: 'app_settings_dashboard_widgets_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{widget?: string, hidden?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_dashboard_widgets', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $widget = DashboardWidgetEnum::tryFrom($payload['widget'] ?? '');

        if (null === $widget) {
            return $this->json([
                'error' => 'Invalid widget',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->hiddenWidgets = $this->applyVisibility($user->hiddenWidgets, $widget, (bool) ($payload['hidden'] ?? false));
        $this->em->flush();

        return $this->json([
            'hiddenWidgets' => $user->hiddenWidgets,
        ]);
    }

    /**
     * @param array<string> $hiddenWidgets
     * @return array<string>
     */
    private function applyVisibility(array $hiddenWidgets, DashboardWidgetEnum $widget, bool $hidden): array
    {
        $withoutWidget = array_values(array_filter($hiddenWidgets, static fn (string $key): bool => $key !== $widget->value));

        return $hidden ? [...$withoutWidget, $widget->value] : $withoutWidget;
    }
}
