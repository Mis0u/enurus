<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsLanguageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/language',
            'fr' => '/reglages/langue',
            'it' => '/impostazioni/lingua',
            'es' => '/ajustes/idioma',
            'pt' => '/definicoes/idioma',
            'de' => '/einstellungen/sprache',
            'nl' => '/instellingen/taal',
            'pl' => '/ustawienia/jezyk',
        ],
        name: 'app_settings_language_update',
        methods: [Request::METHOD_POST],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{locale?: string, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_language', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locale = LocaleAllowedEnum::tryFrom($payload['locale'] ?? '');

        if (null === $locale) {
            return $this->json([
                'error' => 'Invalid locale',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->locale = $locale->value;
        $this->em->flush();

        // Redirection vers la même page Réglages, mais dans la nouvelle locale —
        // nécessaire car le routing est locale-prefixé (/{_locale}/...).
        $redirectUrl = $this->urlGenerator->generate('app_settings', [
            '_locale' => $locale->value,
        ]);

        return $this->json([
            'redirectUrl' => $redirectUrl,
        ]);
    }
}
