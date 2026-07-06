<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Entity\Workout;
use App\Security\Voter\WorkoutVoter;
use App\Service\Entity\WorkoutPhotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

#[IsGranted('ROLE_USER')]
final class WorkoutUploadPhotoController extends AbstractController
{
    public function __construct(
        private readonly WorkoutPhotoService $workoutPhotoService,
    ) {
    }

    #[Route(
        path: '/workout/{id}/photo',
        name: 'workout_upload_photo',
        methods: ['POST'],
    )]
    // EDIT est volontairement utilisé ici, y compris pour la création.
    // Lors de l'upload post-création, le workout est déjà persisté et appartient
    // à l'utilisateur connecté. La vérification de propriété est donc identique
    // dans les deux contextes (création et édition).
    #[IsGranted(WorkoutVoter::EDIT, subject: 'workout')]
    public function __invoke(
        Workout $workout,
        #[MapUploadedFile(
            constraints: [
                new File(
                    maxSize: ImageConstraints::MAX_SIZE_WEIGHT,
                    mimeTypes: ImageConstraints::ALLOWED_MIME_TYPES,
                    maxSizeMessage: 'workout.photo.too_large',
                    mimeTypesMessage: 'workout.photo.invalid_type',
                ),
            ]
        )]
        ?UploadedFile $photo = null,
    ): JsonResponse {
        if (null === $photo) {
            return $this->json(
                [
                    'error' => 'No file provided',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (! $photo->isValid()) {
            return $this->json(
                [
                    'error' => 'Invalid file',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        if (null === $user->id) {
            throw new \LogicException('User id cannot be null.');
        }

        $path = $this->workoutPhotoService->replace(
            $workout,
            $photo,
            $user->id->toRfc4122(),
        );

        return $this->json([
            'path' => $path,
            'url' => \sprintf('/uploads/%s', $path),
        ]);
    }
}
