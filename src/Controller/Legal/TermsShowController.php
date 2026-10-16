<?php

declare(strict_types=1);

namespace App\Controller\Legal;

use App\Service\Utils\ReturnUrlResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page publique, accessible sans authentification (comme ErrorPageController) — les CGU doivent
 * pouvoir être consultées avant inscription.
 */
final class TermsShowController extends AbstractController
{
    public function __construct(
        private readonly ReturnUrlResolver $returnUrlResolver,
    ) {
    }

    #[Route(
        path: [
            'en' => '/terms',
            'fr' => '/cgu',
            'it' => '/termini',
            'es' => '/terminos',
            'pt' => '/termos',
            'de' => '/agb',
            'nl' => '/voorwaarden',
            'pl' => '/regulamin',
        ],
        name: 'app_terms',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(Request $request): Response
    {
        return $this->render('legal/terms.html.twig', [
            'backUrl' => $this->returnUrlResolver->resolve($request, $this->generateUrl('app_login')),
        ]);
    }
}
