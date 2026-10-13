<?php

declare(strict_types=1);

namespace App\Controller\Seo;

use App\Controller\ExerciseGuide\ExerciseGuideIndexController;
use App\Controller\ExerciseGuide\ExerciseGuideShowController;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\ExerciseGuide\ExerciseGuide;
use App\Service\ExerciseGuide\ExerciseGuideCatalog;
use App\Service\ExerciseGuide\ExerciseGuideSlugger;
use App\Service\Seo\LocaleAlternateUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan du site pour les moteurs de recherche : seules les pages publiques utiles à l'arrivée d'un
 * visiteur (la connexion n'apporte rien hors de son compte), fiches exercices comprises, dans ses 8
 * langues quand son contenu est traduit, sinon sa seule version de référence.
 * Hors préfixe de locale (cf. config/routes.yaml) : les moteurs le cherchent à la racine.
 */
final class SitemapController extends AbstractController
{
    private const array PUBLIC_ROUTES = ['app_home', 'app_register', 'app_terms', ExerciseGuideIndexController::ROUTE];

    private const int CACHE_SECONDS = 86400;

    public function __construct(
        private readonly LocaleAlternateUrlGenerator $alternateUrlGenerator,
        private readonly ExerciseGuideCatalog $exerciseGuideCatalog,
        private readonly ExerciseGuideSlugger $exerciseGuideSlugger,
    ) {
    }

    /**
     * `stateless` : en prod, Sentry (LoginListener) lit l'utilisateur connecté sur chaque requête qui
     * ne l'est pas ; la session est alors consultée et Symfony rend la réponse privée, ce qui annulait
     * le cache public ci-dessous. Le sitemap n'utilise jamais de session.
     */
    #[Route(path: '/sitemap.xml', name: 'app_sitemap', methods: [Request::METHOD_GET], format: 'xml', stateless: true)]
    public function __invoke(): Response
    {
        $response = $this->render('seo/sitemap.xml.twig', [
            'pages' => [
                ...array_map(fn (string $route): array => $this->pageAlternates($route), self::PUBLIC_ROUTES),
                ...array_map($this->exerciseGuideAlternates(...), $this->exerciseGuideCatalog->all()),
            ],
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }

    /**
     * @param array<string, mixed> $routeParams paramètres de la page dans une de ses langues
     *
     * @return array{locations: array<array-key, string>, alternates: array<string, string>} une entrée
     *     du sitemap par langue (`locations`), chacune listant toutes les versions plus x-default
     */
    private function pageAlternates(string $route, array $routeParams = []): array
    {
        if (! $this->alternateUrlGenerator->isTranslated($route)) {
            return [
                'locations' => [$this->alternateUrlGenerator->canonical($route, [])],
                'alternates' => [],
            ];
        }

        $locations = $this->alternateUrlGenerator->alternates($route, $routeParams);

        return [
            'locations' => $locations,
            'alternates' => $locations + [
                'x-default' => $this->alternateUrlGenerator->defaultAlternate($route, $routeParams),
            ],
        ];
    }

    /**
     * Les slugs des autres langues sont déduits de la version anglaise par le générateur.
     *
     * @return array{locations: array<array-key, string>, alternates: array<string, string>}
     */
    private function exerciseGuideAlternates(ExerciseGuide $guide): array
    {
        $locale = LocaleAllowedEnum::EN->value;

        return $this->pageAlternates(ExerciseGuideShowController::ROUTE, [
            '_locale' => $locale,
            'slug' => $this->exerciseGuideSlugger->slugFor($guide, $locale),
        ]);
    }
}
