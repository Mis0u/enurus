<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\Workout;
use App\Security\Voter\WorkoutVoter;
use App\Service\Entity\WorkoutPhotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route(
    path: [
        'fr' => '/seance/{id}/photo',
        'en' => '/workout/{id}/photo',
        'it' => '/allenamento/{id}/foto',
        'es' => '/entrenamiento/{id}/foto',
        'pt' => '/treino/{id}/foto',
        'de' => '/training/{id}/foto',
        'nl' => '/training/{id}/foto',
        'pl' => '/trening/{id}/foto',
    ],
    name: 'workout_delete_photo',
    methods: ['DELETE'],
)]
#[IsGranted(WorkoutVoter::EDIT, subject: 'workout')]
final class WorkoutDeletePhotoController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly WorkoutPhotoService $workoutPhotoService,
    ) {
    }

    public function __invoke(Request $request, Workout $workout): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        if (null === $workout->id) {
            throw new \LogicException('Cannot delete the photo of a workout without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'workout_photo_delete_' . $workout->id->toRfc4122());

        $this->workoutPhotoService->remove($workout);

        return $this->json([
            'success' => true,
        ]);
    }
}
