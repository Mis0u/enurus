<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\Request;

/**
 * Jeton CSRF transmis par le JS dans l'en-tête `X-CSRF-Token` (requêtes `fetch`, sans formulaire
 * Symfony) — suppressions, uploads.
 */
trait ValidatesCsrfHeaderTrait
{
    private function denyUnlessValidCsrfToken(Request $request, string $tokenId): void
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (! $this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
