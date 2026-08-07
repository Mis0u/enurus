<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ErrorPageController extends AbstractController
{
    private const array SUPPORTED_STATUS_CODES = [
        Response::HTTP_FORBIDDEN,
        Response::HTTP_NOT_FOUND,
        Response::HTTP_INTERNAL_SERVER_ERROR,
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(\Throwable $exception): Response
    {
        $statusCode = $this->resolveStatusCode($exception);

        return $this->render('error/error.html.twig', [
            'statusCode' => $statusCode,
            'locale' => $this->resolveLocale($statusCode),
        ], new Response('', $statusCode));
    }

    private function resolveStatusCode(\Throwable $exception): int
    {
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;

        return \in_array($statusCode, self::SUPPORTED_STATUS_CODES, true) ? $statusCode : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function resolveLocale(int $statusCode): string
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $user->locale;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (Response::HTTP_NOT_FOUND === $statusCode) {
            // Ce branch n'est plus atteint que par les URLs sans préfixe de locale valide
            // (NotFoundController capte tout le reste via une route catch-all réelle, qui
            // traverse le firewall et rend getUser() fiable — cf. sa docblock). Ici, la requête
            // n'a jamais matché de route, donc ni le firewall ni Request::setLocale() n'ont
            // tourné : getUser() est toujours null et on se rabat sur l'Accept-Language.
            return $request?->getPreferredLanguage(LocaleAllowedEnum::getAllowedLocale()) ?? LocaleAllowedEnum::EN->value;
        }

        return $request?->getLocale() ?? LocaleAllowedEnum::EN->value;
    }
}
