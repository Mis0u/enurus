<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactBroadcast;
use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Message\SendContactBroadcastMessage;
use App\Repository\UserRepository;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ContactThreadComposeService
{
    use AttachesContactImageTrait;
    use CreatesContactThreadTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageUploadService $imageUploadService,
        private ContactMessageBodySanitizerService $contactMessageBodySanitizerService,
        private UserRepository $userRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function composeToSingleUser(
        User $admin,
        User $recipient,
        ContactCategoryEnum $category,
        string $subject,
        string $body,
        ?UploadedFile $image,
    ): ContactThread {
        if (null === $admin->id) {
            throw new \LogicException('Cannot compose a message from an admin without a persisted id.');
        }

        $body = $this->contactMessageBodySanitizerService->sanitize($body);
        $imagePath = $this->uploadContactImage($image, $admin->id->toRfc4122());
        $thread = $this->buildThread($recipient, $admin, $category, $subject, $body, $imagePath, fromAdmin: true);
        $thread->status = ContactThreadStatusEnum::ANSWERED_BY_ADMIN;

        $this->entityManager->persist($thread);
        $this->entityManager->flush();

        return $thread;
    }

    /**
     * Un envoi groupé est toujours en catégorie `INFORMATIVE` (non répondable, cf. ContactThreadVoter)
     * — la catégorie n'est donc jamais demandée à l'appelant ici, contrairement à composeToSingleUser().
     *
     * Seuls le `ContactBroadcast` et l'upload (unique) de l'image sont synchrones, pour un retour
     * immédiat à l'admin — la création des fils par destinataire (potentiellement des centaines)
     * est déportée dans SendContactBroadcastMessageHandler, via Messenger (transport `async`, déjà
     * utilisé pour les emails). Le nombre de destinataires est donc un compte rapide
     * (`UserRepository::countForBroadcast()`), pas le résultat d'une boucle déjà exécutée.
     */
    public function composeToAudience(
        User $admin,
        ContactBroadcastTargetEnum $target,
        ?LocaleAllowedEnum $locale,
        string $subject,
        string $body,
        ?UploadedFile $image,
    ): int {
        if (null === $admin->id) {
            throw new \LogicException('Cannot compose a broadcast from an admin without a persisted id.');
        }

        $body = $this->contactMessageBodySanitizerService->sanitize($body);
        $sourceImagePath = $this->uploadContactImage($image, $admin->id->toRfc4122());

        $broadcast = new ContactBroadcast();
        $broadcast->sentBy = $admin;
        $broadcast->subject = $subject;
        $broadcast->body = $body;
        $broadcast->target = $target;
        $broadcast->locale = $locale;
        $broadcast->recipientCount = $this->userRepository->countForBroadcast($admin->id, $locale?->value);

        $this->entityManager->persist($broadcast);
        $this->entityManager->flush();

        if (null === $broadcast->id) {
            throw new \LogicException('Cannot dispatch a broadcast without a persisted id.');
        }

        $this->messageBus->dispatch(new SendContactBroadcastMessage($broadcast->id->toRfc4122(), $sourceImagePath));

        return $broadcast->recipientCount;
    }
}
