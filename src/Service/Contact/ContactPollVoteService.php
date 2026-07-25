<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactPollOption;
use App\Entity\ContactPollVote;
use App\Entity\ContactThread;
use App\Exception\Contact\AlreadyVotedException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContactPollVoteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * La garde principale est le Voter (`ContactThreadVoter::VOTE`), mais deux requêtes
     * simultanées peuvent toutes les deux la passer avant qu'aucune n'ait flushé — la contrainte
     * unique de `ContactPollVote::$thread` (OneToOne) est le seul rempart réellement atomique,
     * d'où le try/catch plutôt qu'une simple vérification préalable.
     */
    public function vote(ContactThread $thread, ContactPollOption $option): ContactPollVote
    {
        $vote = new ContactPollVote();
        $vote->thread = $thread;
        $vote->option = $option;
        $thread->pollVote = $vote;

        $this->entityManager->persist($vote);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new AlreadyVotedException(previous: $e);
        }

        return $vote;
    }
}
