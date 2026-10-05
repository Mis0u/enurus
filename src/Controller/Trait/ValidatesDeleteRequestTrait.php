<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait ValidatesDeleteRequestTrait
{
    use ValidatesCsrfHeaderTrait;

    private function denyUnlessXmlHttpRequest(Request $request): ?JsonResponse
    {
        if ($request->isXmlHttpRequest()) {
            return null;
        }

        return $this->json([
            'error' => 'XHR only',
        ], Response::HTTP_BAD_REQUEST);
    }
}
