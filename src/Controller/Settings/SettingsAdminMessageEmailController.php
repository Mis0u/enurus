<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interrupteur de l'email annonçant un nouveau message de l'admin, enregistré à la volée. Sans
 * objet pour l'admin, qui ne reçoit jamais cet email.
 */
#[IsGranted('ROLE_USER')]
final class SettingsAdminMessageEmailController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'settings_admin_message_email';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/message-emails',
            'fr' => '/reglages/email-messages',
            'it' => '/impostazioni/email-messaggi',
            'es' => '/ajustes/correo-mensajes',
            'pt' => '/definicoes/email-mensagens',
            'de' => '/einstellungen/nachrichten-email',
            'nl' => '/instellingen/berichten-email',
            'pl' => '/ustawienia/email-wiadomosci',
        ],
        name: 'app_settings_admin_message_email_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }

        /** @var array{enabled?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->emailOnAdminMessage = (bool) ($payload['enabled'] ?? false);
        $this->em->flush();

        return $this->json([
            'enabled' => $user->emailOnAdminMessage,
        ]);
    }
}
