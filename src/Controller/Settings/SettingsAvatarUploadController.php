<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Service\Entity\UserAvatarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

#[IsGranted('ROLE_USER')]
final class SettingsAvatarUploadController extends AbstractController
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
        name: 'app_settings_avatar_upload',
        methods: ['POST'],
    )]
    public function __invoke(
        #[MapUploadedFile(
            constraints: [
                new File(
                    maxSize: ImageConstraints::MAX_SIZE_WEIGHT,
                    mimeTypes: ImageConstraints::ALLOWED_MIME_TYPES,
                    maxSizeMessage: 'settings.avatar.too_large',
                ),
            ]
        )]
        ?UploadedFile $avatar = null,
    ): JsonResponse {
        if (null === $avatar) {
            return $this->json([
                'error' => 'No file provided',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $path = $this->userAvatarService->replace($user, $avatar);

        return $this->json([
            'path' => $path,
            'url' => '/uploads/' . $path,
        ]);
    }
}
