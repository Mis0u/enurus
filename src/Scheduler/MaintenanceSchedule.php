<?php

declare(strict_types=1);

namespace App\Scheduler;

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
 * donc jamais exécutées en production.
 *
 * `->stateful()` persiste la dernière exécution en cache pour survivre à un redémarrage du worker
 * entre deux passages ; `->processOnlyLastMissedRun()` évite un rattrapage en rafale après une
 * longue indisponibilité (seule la dernière occurrence manquée est rejouée).
 */
#[AsSchedule('maintenance')]
final readonly class MaintenanceSchedule implements ScheduleProviderInterface
{
    private const string DAILY_AT_3AM_UTC = '0 3 * * *';

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
            )
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
