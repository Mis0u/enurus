<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Service\ProfileSharing\ShareCodeAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsProfileSharingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShareCodeAssigner $shareCodeAssigner,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/profile-sharing',
            'fr' => '/reglages/partage-de-profil',
            'it' => '/impostazioni/condivisione-profilo',
            'es' => '/ajustes/compartir-perfil',
            'pt' => '/definicoes/partilha-de-perfil',
            'de' => '/einstellungen/profil-teilen',
            'nl' => '/instellingen/profiel-delen',
            'pl' => '/ustawienia/udostepnianie-profilu',
        ],
        name: 'app_settings_profile_sharing_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{action?: string, enabled?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_profile_sharing', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        $response = match ($payload['action'] ?? '') {
            'toggle' => $this->toggle($user, (bool) ($payload['enabled'] ?? false)),
            'regenerate' => $this->regenerate($user),
            default => null,
        };

        if (null === $response) {
            return $this->json([
                'error' => 'Invalid action',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    private function toggle(User $user, bool $enabled): JsonResponse
    {
        $user->isDiscoverable = $enabled;

        if ($enabled && null === $user->shareCode) {
            $this->shareCodeAssigner->assign($user);
        }

        $this->em->flush();

        return $this->json([
            'isDiscoverable' => $user->isDiscoverable,
            'shareCode' => $user->shareCode,
        ]);
    }

    private function regenerate(User $user): JsonResponse
    {
        if (! $user->isDiscoverable) {
            return $this->json([
                'error' => 'Profile sharing is not enabled',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->shareCodeAssigner->assign($user);
        $this->em->flush();

        return $this->json([
            'isDiscoverable' => $user->isDiscoverable,
            'shareCode' => $user->shareCode,
        ]);
    }
}
