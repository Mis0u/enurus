<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche l'envoi des emails d'annonce du résumé annuel — dispatché le 16 décembre à 7h (Paris)
 * par App\Scheduler\MaintenanceSchedule, quelques heures après la génération de minuit.
 */
final readonly class AnnounceYearInReviewsMessage
{
}
