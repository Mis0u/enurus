<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Constraint\ImageConstraints;
use App\Controller\Trait\ValidatesCsrfHeaderTrait;
use App\Entity\User;
use App\Service\Entity\UserAvatarService;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

#[IsGranted('ROLE_USER')]
final class SettingsAvatarUploadController extends AbstractController
{
    use ValidatesCsrfHeaderTrait;

    public const string CSRF_TOKEN_ID = 'settings_avatar_upload';

    public function __construct(
        private readonly UserAvatarService $userAvatarService,
        private readonly FilesystemOperator $defaultStorage,
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
        Request $request,
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
        $this->denyUnlessValidCsrfToken($request, self::CSRF_TOKEN_ID);

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
            'url' => $this->defaultStorage->publicUrl($path),
        ]);
    }
}
