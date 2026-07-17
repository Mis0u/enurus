<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait ValidatesDeleteRequestTrait
{
    private function denyUnlessXmlHttpRequest(Request $request): ?JsonResponse
    {
        if ($request->isXmlHttpRequest()) {
            return null;
        }

        return $this->json([
            'error' => 'XHR only',
        ], Response::HTTP_BAD_REQUEST);
    }

    private function denyUnlessValidCsrfToken(Request $request, string $tokenId): void
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (! $this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
