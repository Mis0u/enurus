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

/**
 * Masque/réaffiche un widget spécifiquement pour le dashboard partagé (connexions), en plus de
 * `SettingsDashboardWidgetsController` qui gère la visibilité sur son propre dashboard — deux
 * réglages indépendants, jamais le même endpoint : `hiddenSharedWidgets` ne fait que restreindre
 * davantage, jamais réafficher un widget déjà masqué partout.
 */
#[IsGranted('ROLE_USER')]
final class SettingsDashboardWidgetSharingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/widgets/sharing',
            'fr' => '/reglages/widgets/partage',
            'it' => '/impostazioni/widget/condivisione',
            'es' => '/ajustes/widgets/compartir',
            'pt' => '/definicoes/widgets/partilha',
            'de' => '/einstellungen/widgets/teilen',
            'nl' => '/instellingen/widgets/delen',
            'pl' => '/ustawienia/widgety/udostepnianie',
        ],
        name: 'app_settings_dashboard_widget_sharing_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{widget?: string, hiddenForShare?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_dashboard_widget_sharing', $payload['_token'] ?? '')) {
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
        $user->hiddenSharedWidgets = $this->applyVisibility($user->hiddenSharedWidgets, $widget, (bool) ($payload['hiddenForShare'] ?? false));
        $this->em->flush();

        return $this->json([
            'hiddenSharedWidgets' => $user->hiddenSharedWidgets,
        ]);
    }

    /**
     * @param array<string> $hiddenSharedWidgets
     * @return array<string>
     */
    private function applyVisibility(array $hiddenSharedWidgets, DashboardWidgetEnum $widget, bool $hiddenForShare): array
    {
        $withoutWidget = array_values(array_filter($hiddenSharedWidgets, static fn (string $key): bool => $key !== $widget->value));

        return $hiddenForShare ? [...$withoutWidget, $widget->value] : $withoutWidget;
    }
}
