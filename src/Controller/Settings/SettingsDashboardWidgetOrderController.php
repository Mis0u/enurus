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
 * Ordre des widgets du dashboard, réorganisés dans Réglages › Widgets du dashboard (poignée ou
 * boutons ↑ ↓). Seuls les widgets débloqués y sont listés : l'ordre enregistré est complété par
 * `DashboardWidgetEnum::inOrder()`, les autres gardant leur place par défaut à la fin.
 */
#[IsGranted('ROLE_USER')]
final class SettingsDashboardWidgetOrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/widgets/order',
            'fr' => '/reglages/widgets/ordre',
            'it' => '/impostazioni/widget/ordine',
            'es' => '/ajustes/widgets/orden',
            'pt' => '/definicoes/widgets/ordem',
            'de' => '/einstellungen/widgets/reihenfolge',
            'nl' => '/instellingen/widgets/volgorde',
            'pl' => '/ustawienia/widgety/kolejnosc',
        ],
        name: 'app_settings_dashboard_widgets_order_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{order?: mixed, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_dashboard_widgets', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $widgets = $this->parseOrder($payload['order'] ?? null);

        if (null === $widgets) {
            return $this->json([
                'error' => 'Invalid widget order',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->widgetOrder = array_map(static fn (DashboardWidgetEnum $widget): string => $widget->value, DashboardWidgetEnum::inOrder($widgets));
        $this->em->flush();

        return $this->json([
            'widgetOrder' => $user->widgetOrder,
        ]);
    }

    /**
     * @return list<string>|null null si l'ordre n'est pas une liste de clés de widgets existants
     */
    private function parseOrder(mixed $order): ?array
    {
        if (! \is_array($order) || ! array_is_list($order)) {
            return null;
        }

        foreach ($order as $key) {
            if (! \is_string($key) || null === DashboardWidgetEnum::tryFrom($key)) {
                return null;
            }
        }

        /** @var list<string> $order */
        return $order;
    }
}
