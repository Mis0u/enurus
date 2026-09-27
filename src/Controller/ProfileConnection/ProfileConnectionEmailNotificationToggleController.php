<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Active ou coupe l'email reçu à chaque demande de connexion (cf. ProfileConnectionRequestNotifier).
 */
#[IsGranted('ROLE_USER')]
final class ProfileConnectionEmailNotificationToggleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/connections/notifications',
            'fr' => '/connexions/notifications',
            'it' => '/connessioni/notifiche',
            'es' => '/conexiones/notificaciones',
            'pt' => '/conexoes/notificacoes',
            'de' => '/verbindungen/benachrichtigungen',
            'nl' => '/verbindingen/meldingen',
            'pl' => '/polaczenia/powiadomienia',
        ],
        name: 'app_profile_connection_email_notification_toggle',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{enabled?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('profile_connection_email_notification_toggle', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->emailOnConnectionRequest = (bool) ($payload['enabled'] ?? false);
        $this->em->flush();

        return $this->json([
            'enabled' => $user->emailOnConnectionRequest,
        ]);
    }
}
