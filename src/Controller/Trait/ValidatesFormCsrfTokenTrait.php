<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\Request;

/**
 * Pour un formulaire HTML classique (jeton dans le champ `_token`), là où
 * `ValidatesDeleteRequestTrait` lit un en-tête XHR. Un jeton invalide donne un 403 : l'attribut
 * `#[IsCsrfTokenValid]`, lui, lève une exception d'authentification que le firewall transforme en
 * redirection vers la page de connexion, y compris pour un utilisateur déjà connecté.
 */
trait ValidatesFormCsrfTokenTrait
{
    private function denyUnlessValidFormCsrfToken(Request $request, string $tokenId): void
    {
        if (! $this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
