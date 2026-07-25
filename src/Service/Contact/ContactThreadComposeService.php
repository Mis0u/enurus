<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactBroadcast;
use App\Entity\ContactPollOption;
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
     * Catégorie choisie par l'appelant (`INFORMATIVE` ou `VOTE`, cf. ContactBroadcastComposeFormType)
     * — non répondable dans les deux cas (cf. ContactThreadVoter). `$pollOptionLabels` et
     * `$pollDurationDays` ne sont utilisés que pour `VOTE`, ignorés sinon.
     *
     * Seuls le `ContactBroadcast` (+ ses `ContactPollOption` pour un sondage) et l'upload (unique)
     * de l'image sont synchrones, pour un retour immédiat à l'admin — la création des fils par
     * destinataire (potentiellement des centaines) est déportée dans SendContactBroadcastMessageHandler,
     * via Messenger (transport `async`, déjà utilisé pour les emails). Le nombre de destinataires est
     * donc un compte rapide (`UserRepository::countForBroadcast()`), pas le résultat d'une boucle déjà
     * exécutée.
     *
     * @param list<string> $pollOptionLabels
     */
    public function composeToAudience(
        User $admin,
        ContactCategoryEnum $category,
        ContactBroadcastTargetEnum $target,
        ?LocaleAllowedEnum $locale,
        string $subject,
        string $body,
        ?UploadedFile $image,
        array $pollOptionLabels = [],
        ?int $pollDurationDays = null,
    ): int {
        if (null === $admin->id) {
            throw new \LogicException('Cannot compose a broadcast from an admin without a persisted id.');
        }

        $body = $this->contactMessageBodySanitizerService->sanitize($body);
        $sourceImagePath = $this->uploadContactImage($image, $admin->id->toRfc4122());

        $broadcast = new ContactBroadcast();
        $broadcast->sentBy = $admin;
        $broadcast->category = $category;
        $broadcast->subject = $subject;
        $broadcast->body = $body;
        $broadcast->target = $target;
        $broadcast->locale = $locale;
        $broadcast->recipientCount = $this->userRepository->countForBroadcast($admin->id, $locale?->value);

        if (ContactCategoryEnum::VOTE === $category) {
            $this->attachPoll($broadcast, $pollOptionLabels, $pollDurationDays);
        }

        $this->entityManager->persist($broadcast);
        $this->entityManager->flush();

        if (null === $broadcast->id) {
            throw new \LogicException('Cannot dispatch a broadcast without a persisted id.');
        }

        $this->messageBus->dispatch(new SendContactBroadcastMessage($broadcast->id->toRfc4122(), $sourceImagePath));

        return $broadcast->recipientCount;
    }

    /**
     * @param list<string> $pollOptionLabels
     */
    private function attachPoll(ContactBroadcast $broadcast, array $pollOptionLabels, ?int $pollDurationDays): void
    {
        if (null === $pollDurationDays) {
            throw new \LogicException('A poll broadcast requires a closing duration.');
        }

        $broadcast->pollClosesAt = (new \DateTimeImmutable())->modify(sprintf('+%d days', $pollDurationDays));

        foreach ($pollOptionLabels as $position => $label) {
            $option = new ContactPollOption();
            $option->label = $label;
            $option->position = $position;
            $broadcast->addPollOption($option);
        }
    }
}
