<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fils de démo couvrant les 3 statuts (en attente de réponse admin, répondu, clôturé) et un
 * message admin non lu (pour tester le badge de la messagerie).
 */
class ContactFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_ADMIN),
            User::class,
        );
        /** @var User $awaitingOwner */
        $awaitingOwner = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_DASHBOARD_SINGLE),
            User::class,
        );
        /** @var User $answeredOwner */
        $answeredOwner = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_ROUTINE_OWNER),
            User::class,
        );
        /** @var User $closedOwner */
        $closedOwner = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_REVERSE_FLY),
            User::class,
        );

        $this->createAwaitingThread($manager, $awaitingOwner);
        $this->createClosedThread($manager, $closedOwner, $admin);

        // $answeredOwner reçoit 3 fils répondus avec message admin non lu — total de 3 non lus,
        // utile pour tester le badge de nav.
        $this->createAnsweredThread(
            $manager,
            $answeredOwner,
            $admin,
            ContactCategoryEnum::SUGGESTION,
            "Ajouter un export CSV de l'historique",
            "Ce serait top de pouvoir exporter l'historique de mes séances en CSV pour le suivre dans un tableur.",
            "Bonne idée, c'est noté ! Je l'ajoute à la liste des prochaines fonctionnalités.",
        );
        $this->createAnsweredThread(
            $manager,
            $answeredOwner,
            $admin,
            ContactCategoryEnum::LOVE,
            "Juste pour dire que l'app est géniale",
            "Je voulais juste dire un grand merci, l'application est top et super pratique au quotidien !",
            'Ça nous touche énormément, merci infiniment pour ce message !',
        );
        $this->createAnsweredThread(
            $manager,
            $answeredOwner,
            $admin,
            ContactCategoryEnum::BUG,
            "Le bouton d'export CSV ne fonctionne plus",
            "Depuis la dernière mise à jour, le bouton d'export CSV sur la page Mes séances ne réagit plus au clic.",
            'Merci pour le signalement, on regarde ça de ce pas et on te tient au courant.',
        );

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    private function createAwaitingThread(ObjectManager $manager, User $owner): void
    {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = ContactCategoryEnum::BUG;
        $thread->subject = 'Le tonnage affiché est incohérent';

        $message = new ContactThreadMessage();
        $message->author = $owner;
        $message->fromAdmin = false;
        $message->body = 'Bonjour, je constate que le tonnage total de ma dernière séance ne correspond pas à la somme de mes séries. Pouvez-vous vérifier ?';

        $thread->addMessage($message);

        $manager->persist($thread);
    }

    private function createAnsweredThread(
        ObjectManager $manager,
        User $owner,
        User $admin,
        ContactCategoryEnum $category,
        string $subject,
        string $userBody,
        string $adminBody,
    ): void {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = $category;
        $thread->subject = $subject;

        $userMessage = new ContactThreadMessage();
        $userMessage->author = $owner;
        $userMessage->fromAdmin = false;
        $userMessage->body = $userBody;

        $thread->addMessage($userMessage);

        $adminMessage = new ContactThreadMessage();
        $adminMessage->author = $admin;
        $adminMessage->fromAdmin = true;
        $adminMessage->body = $adminBody;

        $thread->addMessage($adminMessage);
        $thread->status = ContactThreadStatusEnum::ANSWERED_BY_ADMIN;

        $manager->persist($thread);
    }

    private function createClosedThread(ObjectManager $manager, User $owner, User $admin): void
    {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = ContactCategoryEnum::QUESTION;
        $thread->subject = 'Question sur le calcul des PR';

        $userMessage = new ContactThreadMessage();
        $userMessage->author = $owner;
        $userMessage->fromAdmin = false;
        $userMessage->body = 'Le PR est calculé sur le poids seul, ou poids x reps ?';

        $thread->addMessage($userMessage);

        $adminMessage = new ContactThreadMessage();
        $adminMessage->author = $admin;
        $adminMessage->fromAdmin = true;
        $adminMessage->body = "Sur le poids seul, c'est la convention en musculation. Je clôture ce fil, n'hésite pas à en ouvrir un nouveau si besoin !";
        $adminMessage->readAt = new \DateTimeImmutable();

        $thread->addMessage($adminMessage);
        $thread->status = ContactThreadStatusEnum::CLOSED;
        $thread->closedAt = new \DateTimeImmutable();

        $manager->persist($thread);
    }
}
