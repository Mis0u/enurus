<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsWeightUnitController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/weight-unit',
            'fr' => '/reglages/unite-poids',
            'it' => '/impostazioni/unita-peso',
            'es' => '/ajustes/unidad-peso',
            'pt' => '/definicoes/unidade-peso',
            'de' => '/einstellungen/gewichtseinheit',
            'nl' => '/instellingen/gewichtseenheid',
            'pl' => '/ustawienia/jednostka-wagi',
        ],
        name: 'app_settings_weight_unit_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{unit?: string, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_weight_unit', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $unit = UnitOfMeasureEnum::tryFrom($payload['unit'] ?? '');

        if (null === $unit) {
            return $this->json([
                'error' => 'Invalid unit',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->unitOfMeasure = $unit;
        $this->em->flush();

        return $this->json([
            'unit' => $user->unitOfMeasure->value,
        ]);
    }
}
