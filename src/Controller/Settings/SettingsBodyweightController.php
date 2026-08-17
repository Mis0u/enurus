<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Service\Utils\WeightConverterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
final class SettingsBodyweightController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly WeightConverterService $weightConverter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/bodyweight',
            'fr' => '/reglages/poids-du-corps',
            'it' => '/impostazioni/peso-corporeo',
            'es' => '/ajustes/peso-corporal',
            'pt' => '/definicoes/peso-corporal',
            'de' => '/einstellungen/koerpergewicht',
            'nl' => '/instellingen/lichaamsgewicht',
            'pl' => '/ustawienia/masa-ciala',
        ],
        name: 'app_settings_bodyweight_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{bodyweight?: string|float|null, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_bodyweight', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        $rawValue = trim((string) ($payload['bodyweight'] ?? ''));

        if ('' === $rawValue) {
            $user->bodyweightKg = null;
            $this->em->flush();

            return $this->json([
                'bodyweight' => null,
            ]);
        }

        if (! is_numeric($rawValue)) {
            return $this->json([
                'errors' => ['user.bodyweight.invalid'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bodyweightKg = $this->weightConverter->convertToKg((float) $rawValue, $user->unitOfMeasure);

        $violations = $this->validator->validate($bodyweightKg, [
            new Range(
                min: User::BODYWEIGHT_MIN_KG,
                max: User::BODYWEIGHT_MAX_KG,
                notInRangeMessage: 'user.bodyweight.range',
            ),
        ]);

        if (0 < \count($violations)) {
            return $this->json(
                [
                    'errors' => [(string) $violations->get(0)->getMessage()],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->bodyweightKg = $bodyweightKg;
        $this->em->flush();

        return $this->json([
            'bodyweight' => $this->weightConverter->convertToLbs($bodyweightKg, $user->unitOfMeasure),
        ]);
    }
}
