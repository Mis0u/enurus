<?php

declare(strict_types=1);

namespace App\Controller\Seo;

use App\Service\Seo\LocaleAlternateUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan du site pour les moteurs de recherche : seules les pages publiques utiles à l'arrivée d'un
 * visiteur (la connexion n'apporte rien hors de son compte), chacune dans ses 8 langues.
 * Hors préfixe de locale (cf. config/routes.yaml) : les moteurs le cherchent à la racine.
 */
final class SitemapController extends AbstractController
{
    private const array PUBLIC_ROUTES = ['app_home', 'app_register', 'app_terms'];

    private const int CACHE_SECONDS = 86400;

    public function __construct(
        private readonly LocaleAlternateUrlGenerator $alternateUrlGenerator,
    ) {
    }

    #[Route(path: '/sitemap.xml', name: 'app_sitemap', methods: [Request::METHOD_GET], format: 'xml')]
    public function __invoke(): Response
    {
        $response = $this->render('seo/sitemap.xml.twig', [
            'pages' => array_map($this->pageAlternates(...), self::PUBLIC_ROUTES),
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }

    /**
     * @return array{locations: array<string, string>, alternates: array<string, string>} une entrée
     *     du sitemap par langue (`locations`), chacune listant toutes les versions plus x-default
     */
    private function pageAlternates(string $route): array
    {
        $locations = $this->alternateUrlGenerator->alternates($route);

        return [
            'locations' => $locations,
            'alternates' => $locations + [
                'x-default' => $this->alternateUrlGenerator->defaultAlternate($route),
            ],
        ];
    }
}
