<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Enum\Entity\User\GenderEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsGenderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/gender',
            'fr' => '/reglages/genre',
            'it' => '/impostazioni/genere',
            'es' => '/ajustes/genero',
            'pt' => '/definicoes/genero',
            'de' => '/einstellungen/geschlecht',
            'nl' => '/instellingen/geslacht',
            'pl' => '/ustawienia/plec',
        ],
        name: 'app_settings_gender_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{gender?: string, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_gender', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $gender = GenderEnum::tryFrom($payload['gender'] ?? '');

        if (null === $gender) {
            return $this->json([
                'error' => 'Invalid gender',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->gender = $gender;
        $this->em->flush();

        return $this->json([
            'gender' => $user->gender->value,
        ]);
    }
}
