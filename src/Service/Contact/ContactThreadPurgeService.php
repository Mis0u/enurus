<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Repository\ContactThreadRepository;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\Clock\now;

final readonly class ContactThreadPurgeService
{
    private const int RETENTION_MONTHS = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactThreadRepository $contactThreadRepository,
        private ImageUploadService $imageUploadService,
    ) {
    }

    public function purgeClosed(): int
    {
        $threshold = now()->modify(sprintf('-%d months', self::RETENTION_MONTHS));
        $threads = $this->contactThreadRepository->findClosedBefore($threshold);

        $imagePaths = [];

        foreach ($threads as $thread) {
            foreach ($thread->messages as $message) {
                $imagePaths[] = $message->imagePath;
            }

            $this->entityManager->remove($thread);
        }

        $this->entityManager->flush();

        foreach ($imagePaths as $imagePath) {
            $this->imageUploadService->delete($imagePath);
        }

        return \count($threads);
    }
}
