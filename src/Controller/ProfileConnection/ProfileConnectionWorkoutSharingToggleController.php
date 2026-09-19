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

#[IsGranted('ROLE_USER')]
final class ProfileConnectionWorkoutSharingToggleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/connections/sharing/workouts',
            'fr' => '/connexions/partage/seances',
            'it' => '/connessioni/condivisione/allenamenti',
            'es' => '/conexiones/compartir/entrenamientos',
            'pt' => '/conexoes/partilha/treinos',
            'de' => '/verbindungen/teilen/trainings',
            'nl' => '/verbindingen/delen/trainingen',
            'pl' => '/polaczenia/udostepnianie/treningi',
        ],
        name: 'app_profile_connection_workout_sharing_toggle',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{shareWorkouts?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('profile_connection_workout_sharing_toggle', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $shareWorkouts = (bool) ($payload['shareWorkouts'] ?? false);

        if ($shareWorkouts && ! $user->isDiscoverable) {
            return $this->json([
                'error' => 'Profile sharing is not enabled',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->shareWorkouts = $shareWorkouts;

        // Cascade : l'affichage des widgets sur le dashboard partagé dépend aussi du partage des
        // séances — ne peut jamais rester actif une fois ce partage secondaire coupé.
        if (! $shareWorkouts) {
            $user->hiddenSharedWidgets = array_map(
                static fn (DashboardWidgetEnum $widget): string => $widget->value,
                DashboardWidgetEnum::cases(),
            );
        }

        $this->em->flush();

        return $this->json([
            'shareWorkouts' => $user->shareWorkouts,
            'hiddenSharedWidgets' => $user->hiddenSharedWidgets,
        ]);
    }
}
