<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Workout;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class WorkoutPhotoService
{
    private const string WORKOUTS = 'workouts';

    public function __construct(
        private ImageUploadService $imageUploadService,
        private EntityManagerInterface $em,
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

    /**
     * Retire la photo d'une séance sans la supprimer elle-même — contrairement à
     * `deletePhoto()`, appelé quand le workout entier disparaît (pas de flush nécessaire là).
     */
    public function remove(Workout $workout): void
    {
        $oldPath = $workout->photoPath;

        $workout->photoPath = null;
        $this->em->flush();

        $this->imageUploadService->delete($oldPath);
    }
}
