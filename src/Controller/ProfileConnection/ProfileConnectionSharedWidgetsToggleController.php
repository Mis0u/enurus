<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

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
 * Masque/réaffiche un widget spécifiquement sur le dashboard partagé (connexions) — réglage
 * indépendant de `SettingsDashboardWidgetsController` (visibilité sur son propre dashboard) :
 * `hiddenSharedWidgets` ne fait que restreindre davantage, jamais réafficher un widget déjà masqué
 * partout. Vit dans `App\Controller\ProfileConnection` et non `Settings` : c'est un réglage du
 * partage, proposé depuis la page « Mes connexions », pas depuis les réglages généraux.
 */
#[IsGranted('ROLE_USER')]
final class ProfileConnectionSharedWidgetsToggleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/connections/sharing/widgets',
            'fr' => '/connexions/partage/widgets',
            'it' => '/connessioni/condivisione/widget',
            'es' => '/conexiones/compartir/widgets',
            'pt' => '/conexoes/partilha/widgets',
            'de' => '/verbindungen/teilen/widgets',
            'nl' => '/verbindingen/delen/widgets',
            'pl' => '/polaczenia/udostepnianie/widgety',
        ],
        name: 'app_profile_connection_shared_widgets_toggle',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{widget?: string, hiddenForShare?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('profile_connection_shared_widgets', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $widget = DashboardWidgetEnum::tryFrom($payload['widget'] ?? '');

        if (null === $widget || ! $widget->isShareable()) {
            return $this->json([
                'error' => 'Invalid widget',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hiddenForShare = (bool) ($payload['hiddenForShare'] ?? false);

        /** @var User $user */
        $user = $this->getUser();

        if (! $hiddenForShare && (! $user->isDiscoverable || ! $user->shareWorkouts)) {
            return $this->json([
                'error' => 'Sharing is not enabled',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->hiddenSharedWidgets = $this->applyVisibility($user->hiddenSharedWidgets, $widget, $hiddenForShare);
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
