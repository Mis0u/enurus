<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Workout;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class WorkoutPhotoService
{
    private const string WORKOUTS = 'workouts';

    public function __construct(
        private readonly ImageUploadService $imageUploadService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function replace(Workout $workout, UploadedFile $file, string $ownerId): string
    {
        $oldPath = $workout->photoPath;

        $path = $this->imageUploadService->upload($file, self::WORKOUTS, $ownerId);

        $workout->photoPath = $path;
        $this->em->flush();

        // Suppression après flush intentionnellement :
        // si le flush échoue, l'ancienne photo est préservée
        $this->imageUploadService->delete($oldPath);

        return $path;
    }

    public function deletePhoto(Workout $workout): void
    {
        $this->imageUploadService->delete($workout->photoPath);
    }
}
