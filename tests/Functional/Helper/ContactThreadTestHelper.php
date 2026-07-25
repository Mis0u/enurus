<?php

declare(strict_types=1);

namespace App\Tests\Functional\Helper;

use App\Entity\ContactBroadcast;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

final class ContactThreadTestHelper
{
    public static function createThread(
        EntityManagerInterface $entityManager,
        User $owner,
        string $subject = 'Sujet de test',
        ContactThreadStatusEnum $status = ContactThreadStatusEnum::AWAITING_ADMIN_REPLY,
        ContactCategoryEnum $category = ContactCategoryEnum::BUG,
        ?ContactBroadcast $broadcast = null,
    ): ContactThread {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = $category;
        $thread->subject = $subject;
        $thread->status = $status;
        $thread->broadcast = $broadcast;

        $message = new ContactThreadMessage();
        $message->author = $owner;
        $message->fromAdmin = false;
        $message->body = 'Message de test suffisamment long pour la validation du formulaire.';

        $thread->addMessage($message);

        $entityManager->persist($thread);
        $entityManager->flush();

        return $thread;
    }

    public static function addAdminMessage(
        EntityManagerInterface $entityManager,
        ContactThread $thread,
        User $admin,
        ?\DateTimeImmutable $readAt = null,
        ?string $body = null,
    ): ContactThreadMessage {
        $message = new ContactThreadMessage();
        $message->author = $admin;
        $message->fromAdmin = true;
        $message->body = $body ?? "Réponse de test de l'admin, suffisamment longue pour la validation.";
        $message->readAt = $readAt;

        $thread->addMessage($message);
        $thread->status = ContactThreadStatusEnum::ANSWERED_BY_ADMIN;

        $entityManager->flush();

        return $message;
    }
}
