<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ContactBroadcast;
use App\Entity\ContactPollOption;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Exception\Translation\TranslationFailedException;
use App\Message\SendContactBroadcastMessage;
use App\Repository\ContactBroadcastRepository;
use App\Repository\UserRepository;
use App\Service\Contact\CreatesContactThreadTrait;
use App\Service\Translation\BroadcastTranslationFailureNotifierService;
use App\Service\Translation\DeepLTranslationService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Crée les fils individuels d'une diffusion admin en tâche de fond — le ContactBroadcast et
 * l'image source (si présente) sont déjà persistés de façon synchrone
 * (ContactThreadComposeService::composeToAudience()), seule la boucle par destinataire (coûteuse :
 * potentiellement des centaines de créations de fil + copies d'image) est déportée ici.
 *
 * Pour un ciblage "tous les utilisateurs" (jamais pour un ciblage d'une locale précise, ni pour un
 * message 1to1), le texte français est traduit une fois par langue distincte parmi les
 * destinataires, avant toute création de fil — si une traduction échoue, rien n'est encore
 * persisté : l'exception se propage telle quelle, le retry Messenger repart de zéro sans jamais
 * envoyer un message partiel. Les libellés des options de sondage (`ContactPollOption`, un jeu
 * unique partagé par tous les destinataires, contrairement au sujet/corps dupliqués par fil) sont
 * traduits dans le même appel DeepL que le sujet/corps, puis stockés sur l'entité elle-même
 * (`translatedLabels[locale]`) — affichés ensuite via `ContactPollOption::labelFor()` selon la
 * langue du visiteur.
 */
#[AsMessageHandler]
final readonly class SendContactBroadcastMessageHandler
{
    use CreatesContactThreadTrait;

    /**
     * Nombre de textes envoyés à DeepL avant les libellés d'options de sondage (sujet + corps),
     * utilisé pour retrouver la traduction de chaque option dans la liste de résultats.
     */
    private const int TEXTS_BEFORE_POLL_OPTIONS = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactBroadcastRepository $contactBroadcastRepository,
        private UserRepository $userRepository,
        private ImageUploadService $imageUploadService,
        private DeepLTranslationService $translationService,
        private BroadcastTranslationFailureNotifierService $failureNotifier,
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

        $translationsByLocale = $this->buildTranslationsByLocale($broadcast, $recipients);

        foreach ($recipients as $recipient) {
            [$subject, $body] = $translationsByLocale[$recipient->locale] ?? [$broadcast->subject, $broadcast->body];

            $imagePath = null !== $message->sourceImagePath
                ? $this->imageUploadService->copy($message->sourceImagePath, 'contact', $admin->id->toRfc4122())
                : null;

            $thread = $this->buildThread(
                $recipient,
                $admin,
                $broadcast->category,
                $subject,
                $body,
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

    /**
     * @param list<User> $recipients
     *
     * @return array<string, array{0: string, 1: string}> texte [sujet, corps] traduit, indexé par
     *                                                     locale — vide si le ciblage n'est pas
     *                                                     "tous les utilisateurs"
     */
    private function buildTranslationsByLocale(ContactBroadcast $broadcast, array $recipients): array
    {
        if (ContactBroadcastTargetEnum::ALL !== $broadcast->target) {
            return [];
        }

        $pollOptions = array_values($broadcast->pollOptions->toArray());
        $translationsByLocale = [];

        foreach (array_unique(array_map(static fn (User $recipient): string => $recipient->locale, $recipients)) as $localeValue) {
            if (LocaleAllowedEnum::FR->value === $localeValue) {
                continue;
            }

            $targetLocale = LocaleAllowedEnum::from($localeValue);
            $texts = [$broadcast->subject, $broadcast->body, ...array_map(
                static fn (ContactPollOption $option): string => $option->label,
                $pollOptions,
            )];

            try {
                $translated = $this->translationService->translate($texts, $targetLocale);
            } catch (TranslationFailedException $e) {
                $this->failureNotifier->notify($broadcast, $targetLocale, $e);

                throw $e;
            }

            $translationsByLocale[$localeValue] = [$translated[0], $translated[1]];

            foreach ($pollOptions as $index => $option) {
                $option->translatedLabels = [
                    ...$option->translatedLabels,
                    $localeValue => $translated[self::TEXTS_BEFORE_POLL_OPTIONS + $index],
                ];
            }
        }

        return $translationsByLocale;
    }
}
