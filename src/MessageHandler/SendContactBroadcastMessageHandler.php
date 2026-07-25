<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ContactBroadcast;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Message\SendContactBroadcastMessage;
use App\Repository\ContactBroadcastRepository;
use App\Repository\UserRepository;
use App\Service\Contact\CreatesContactThreadTrait;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Crée les fils individuels d'une diffusion admin en tâche de fond — le ContactBroadcast et
 * l'image source (si présente) sont déjà persistés de façon synchrone
 * (ContactThreadComposeService::composeToAudience()), seule la boucle par destinataire (coûteuse :
 * potentiellement des centaines de créations de fil + copies d'image) est déportée ici.
 */
#[AsMessageHandler]
final readonly class SendContactBroadcastMessageHandler
{
    use CreatesContactThreadTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactBroadcastRepository $contactBroadcastRepository,
        private UserRepository $userRepository,
        private ImageUploadService $imageUploadService,
    ) {
    }

    public function __invoke(SendContactBroadcastMessage $message): void
    {
        $broadcast = $this->contactBroadcastRepository->find(Uuid::fromString($message->broadcastId));

        if (! $broadcast instanceof ContactBroadcast) {
            return;
        }

        $admin = $broadcast->sentBy;

        if (null === $admin->id) {
            throw new \LogicException('Cannot process a broadcast sent by an admin without a persisted id.');
        }

        $recipients = $this->userRepository->findAllForBroadcast($admin->id, $broadcast->locale?->value);

        foreach ($recipients as $recipient) {
            $imagePath = null !== $message->sourceImagePath
                ? $this->imageUploadService->copy($message->sourceImagePath, 'contact', $admin->id->toRfc4122())
                : null;

            $thread = $this->buildThread(
                $recipient,
                $admin,
                $broadcast->category,
                $broadcast->subject,
                $broadcast->body,
                $imagePath,
                fromAdmin: true,
                broadcast: $broadcast,
            );
            $thread->status = ContactThreadStatusEnum::ANSWERED_BY_ADMIN;

            $this->entityManager->persist($thread);
        }

        $this->entityManager->flush();

        if (null !== $message->sourceImagePath) {
            $this->imageUploadService->delete($message->sourceImagePath);
        }
    }
}
