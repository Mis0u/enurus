<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsIndexController extends AbstractController
{
    #[Route(
        path: [
            'en' => '/settings',
            'fr' => '/reglages',
            'it' => '/impostazioni',
            'es' => '/ajustes',
            'pt' => '/definicoes',
            'de' => '/einstellungen',
            'nl' => '/instellingen',
            'pl' => '/ustawienia',
        ],
        name: 'app_settings',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('settings/index.html.twig', [
            'user' => $user,
            'nicknameMinLength' => User::NICKNAME_MIN_LENGTH,
            'nicknameMaxLength' => User::NICKNAME_MAX_LENGTH,
            'avatarMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
            'avatarAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
        ]);
    }
}
