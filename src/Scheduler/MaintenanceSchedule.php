<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\AnnounceYearInReviewsMessage;
use App\Message\GenerateYearInReviewsMessage;
use App\Message\PurgeClosedContactThreadsMessage;
use App\Message\PurgeExpiredAccountDeletionsMessage;
use App\Message\PurgeExpiredAccountDeletionTracesMessage;
use App\Message\PurgeExpiredUnverifiedAccountsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Purges quotidiennes de données périmées (comptes en suppression, traces anti-réinscription,
 * comptes jamais vérifiés, fils de contact clôturés) — avant ce chantier, les commandes
 * `app:*:purge` existaient mais n'étaient déclenchées par rien (ni cron, ni Scalingo scheduler),
 * donc jamais exécutées en production. Plus la génération annuelle des résumés « Ton année », le
 * 16 décembre à minuit heure de Paris, et leurs emails d'annonce le même jour à 7h (cf.
 * `YearInReviewCalendar`).
 *
 * `->stateful()` persiste la dernière exécution en cache pour survivre à un redémarrage du worker
 * entre deux passages ; `->processOnlyLastMissedRun()` évite un rattrapage en rafale après une
 * longue indisponibilité (seule la dernière occurrence manquée est rejouée).
 */
#[AsSchedule('maintenance')]
final readonly class MaintenanceSchedule implements ScheduleProviderInterface
{
    private const string DAILY_AT_3AM_UTC = '0 3 * * *';

    private const string DECEMBER_16TH_AT_MIDNIGHT = '0 0 16 12 *';

    private const string DECEMBER_16TH_AT_7AM = '0 7 16 12 *';

    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron(self::DAILY_AT_3AM_UTC, new PurgeExpiredAccountDeletionsMessage(), timezone: 'UTC'),
                RecurringMessage::cron(self::DAILY_AT_3AM_UTC, new PurgeExpiredAccountDeletionTracesMessage(), timezone: 'UTC'),
                RecurringMessage::cron(self::DAILY_AT_3AM_UTC, new PurgeExpiredUnverifiedAccountsMessage(), timezone: 'UTC'),
                RecurringMessage::cron(self::DAILY_AT_3AM_UTC, new PurgeClosedContactThreadsMessage(), timezone: 'UTC'),
                RecurringMessage::cron(self::DECEMBER_16TH_AT_MIDNIGHT, new GenerateYearInReviewsMessage(), timezone: 'Europe/Paris'),
                RecurringMessage::cron(self::DECEMBER_16TH_AT_7AM, new AnnounceYearInReviewsMessage(), timezone: 'Europe/Paris'),
            )
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
