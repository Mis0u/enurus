<?php

declare(strict_types=1);

namespace App\Controller\Legal;

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
    public function __invoke(): Response
    {
        return $this->render('legal/terms.html.twig');
    }
}
