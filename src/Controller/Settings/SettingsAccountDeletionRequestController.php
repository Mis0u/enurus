<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Service\Entity\AccountDeletionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsAccountDeletionRequestController extends AbstractController
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/account/deletion',
            'fr' => '/reglages/compte/suppression',
            'it' => '/impostazioni/account/eliminazione',
            'es' => '/ajustes/cuenta/eliminacion',
            'pt' => '/definicoes/conta/eliminacao',
            'de' => '/einstellungen/konto/loeschung',
            'nl' => '/instellingen/account/verwijdering',
            'pl' => '/ustawienia/konto/usuniecie',
        ],
        name: 'app_settings_account_deletion_request',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->request->get('_token');

        if (! $this->isCsrfTokenValid('account_deletion', \is_string($token) ? $token : null)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        $this->accountDeletionService->requestDeletion($user);

        return $this->json([
            'logoutUrl' => $this->generateUrl('app_logout'),
        ]);
    }
}
