<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactBroadcast;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;

/**
 * Construit un ContactThread + son premier message, sans persist()/flush() (laissé à la charge de
 * l'appelant, qui peut vouloir un flush unique pour plusieurs fils — cf. diffusion admin). Partagé
 * entre ContactThreadService (création par un utilisateur) et ContactThreadComposeService
 * (création par l'admin, où owner et author diffèrent). L'image est déjà uploadée par l'appelant
 * (chemin de stockage resolu) — ce trait n'a pas la responsabilité de l'upload.
 */
trait CreatesContactThreadTrait
{
    private function buildThread(
        User $owner,
        User $author,
        ContactCategoryEnum $category,
        string $subject,
        string $body,
        ?string $imagePath,
        bool $fromAdmin,
        ?ContactBroadcast $broadcast = null,
    ): ContactThread {
        if (null === $author->id) {
            throw new \LogicException('Cannot create a contact thread message for an author without a persisted id.');
        }

        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = $category;
        $thread->subject = $subject;
        $thread->broadcast = $broadcast;

        $message = new ContactThreadMessage();
        $message->author = $author;
        $message->fromAdmin = $fromAdmin;
        $message->body = $body;
        $message->imagePath = $imagePath;

        $thread->addMessage($message);

        return $thread;
    }
}
