<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Service\Entity\UserAvatarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsAvatarDeleteController extends AbstractController
{
    public function __construct(
        private readonly UserAvatarService $userAvatarService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/avatar',
            'fr' => '/reglages/avatar',
            'it' => '/impostazioni/avatar',
            'es' => '/ajustes/avatar',
            'pt' => '/definicoes/avatar',
            'de' => '/einstellungen/avatar',
            'nl' => '/instellingen/avatar',
            'pl' => '/ustawienia/avatar',
        ],
        name: 'app_settings_avatar_delete',
        methods: [Request::METHOD_DELETE],
    )]
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->userAvatarService->remove($user);

        return $this->json([
            'success' => true,
        ], Response::HTTP_OK);
    }
}
