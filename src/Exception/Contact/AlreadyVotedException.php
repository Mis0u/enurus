<?php

declare(strict_types=1);

namespace App\Exception\Contact;

/**
 * Levée quand un second vote atteint quand même `ContactPollVoteService::vote()` malgré la garde
 * du Voter (course entre deux requêtes simultanées) — la contrainte unique de
 * `ContactPollVote::$thread` (OneToOne) rejette l'insertion en base, seul rempart réellement fiable.
 */
final class AlreadyVotedException extends \RuntimeException
{
}
