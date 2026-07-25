<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContactBroadcast;
use App\Entity\ContactPollOption;
use App\Entity\ContactPollVote;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Sondage clôturé de démonstration — envoyé (fictivement) il y a un mois, terminé hier. Tous les
 * destinataires ont voté sauf un (cf. NON_VOTER_EMAIL), pour visualiser un taux de participation
 * réaliste (< 100%) sur les graphiques admin. Inclut délibérément les comptes restreints
 * (UserFixtures::USER_RESTRICTED_*) parmi les votants : le Voter autorise aujourd'hui le vote même
 * pour un compte bloqué (la restriction ne s'applique qu'à REPLY, pas à VOTE) — cette fixture
 * matérialise ce choix pour qu'il soit visible/discutable, pas pour le trancher elle-même.
 */
final class ContactPollFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Choix arbitraire — n'importe quel autre compte aurait fait l'affaire, celui-ci a juste
     * l'avantage d'avoir déjà une référence Doctrine fixtures posée par UserFixtures.
     */
    private const string NON_VOTER_EMAIL = UserFixtures::USER_ROUTINE_OTHER;

    /**
     * @var array<string, int> libellé => nombre de votes, somme = nombre de votants
     */
    private const array VOTE_DISTRIBUTION = [
        'Turc' => 9,
        'Norvégien' => 7,
        'Suédois' => 6,
        'Roumain' => 3,
    ];

    public function load(ObjectManager $manager): void
    {
        if (! $manager instanceof EntityManagerInterface) {
            throw new \LogicException('ContactPollFixtures requires a Doctrine ORM EntityManager.');
        }

        /** @var User $admin */
        $admin = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_ADMIN),
            User::class,
        );

        $recipients = $this->findAllNonAdminUsers($manager);

        $broadcast = new ContactBroadcast();
        $broadcast->sentBy = $admin;
        $broadcast->category = ContactCategoryEnum::VOTE;
        $broadcast->subject = "Choix d'une nouvelle traduction";
        $broadcast->body = 'Je te laisse faire ton choix sur la prochaine traduction du site';
        $broadcast->target = ContactBroadcastTargetEnum::ALL;
        $broadcast->recipientCount = \count($recipients);
        $broadcast->pollClosesAt = new \DateTimeImmutable('-1 day');

        $options = [];
        foreach (array_keys(self::VOTE_DISTRIBUTION) as $position => $label) {
            $option = new ContactPollOption();
            $option->label = $label;
            $option->position = $position;
            $broadcast->addPollOption($option);
            $options[$label] = $option;
        }

        $manager->persist($broadcast);

        $voteQueue = $this->buildVoteQueue($options);

        foreach ($recipients as $recipient) {
            $thread = new ContactThread();
            $thread->owner = $recipient;
            $thread->category = ContactCategoryEnum::VOTE;
            $thread->subject = $broadcast->subject;
            $thread->broadcast = $broadcast;
            $thread->status = ContactThreadStatusEnum::ANSWERED_BY_ADMIN;
            $thread->addMessage($this->buildMessage($admin, $broadcast->body));

            $manager->persist($thread);

            if (self::NON_VOTER_EMAIL === $recipient->email) {
                continue;
            }

            $option = array_shift($voteQueue);

            if (null === $option) {
                continue;
            }

            $vote = new ContactPollVote();
            $vote->thread = $thread;
            $vote->option = $option;
            $thread->pollVote = $vote;

            $manager->persist($vote);
        }

        $manager->flush();

        // ContactBroadcast::$sentAt n'a pas de setter (immuable hors construction, cf. l'entité) —
        // seul un fil réellement envoyé aujourd'hui doit avoir une date "maintenant" ; ici on
        // matérialise volontairement un envoi passé, donc mise à jour directe en base.
        $manager->getConnection()->executeStatement(
            'UPDATE contact_broadcast SET sent_at = :sentAt WHERE id = :id',
            [
                'sentAt' => (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s'),
                'id' => (string) $broadcast->id,
            ],
        );
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    /**
     * Exclut l'admin (jamais son propre destinataire) et `user-fixture-2/3@test.com` — ces deux
     * comptes servent de "bac à sable" partagé à quasiment tous les tests de messagerie
     * (ContactThreadListControllerTest, ContactThreadReplyControllerTest, etc.), qui construisent
     * leur propre état de fil en supposant une boîte vide au départ. Les inclure ici casserait ces
     * suppositions dans la base de test (les fixtures y sont chargées telles quelles).
     *
     * @return list<User>
     */
    private function findAllNonAdminUsers(ObjectManager $manager): array
    {
        /** @var list<User> $users */
        $users = $manager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.email != :admin')
            ->andWhere('u.email NOT IN (:reserved)')
            ->setParameter('admin', UserFixtures::USER_ADMIN)
            ->setParameter('reserved', ['user-fixture-2@test.com', 'user-fixture-3@test.com'])
            ->getQuery()
            ->getResult()
        ;

        return $users;
    }

    /**
     * @param array<string, ContactPollOption> $options
     * @return list<ContactPollOption>
     */
    private function buildVoteQueue(array $options): array
    {
        $queue = [];
        foreach (self::VOTE_DISTRIBUTION as $label => $count) {
            for ($i = 0; $count > $i; ++$i) {
                $queue[] = $options[$label];
            }
        }

        return $queue;
    }

    private function buildMessage(User $admin, string $body): ContactThreadMessage
    {
        $message = new ContactThreadMessage();
        $message->author = $admin;
        $message->fromAdmin = true;
        $message->body = $body;

        return $message;
    }
}
