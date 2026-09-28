<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

/**
 * Objectif de régularité en cours (ou à venir) et historique des défis terminés d'un utilisateur,
 * partagé par l'onglet Calendrier et le widget du dashboard.
 */
final readonly class RegularityGoalOverview
{
    /**
     * @param list<RegularityGoalProgress> $history défis terminés, les plus récents en premier
     */
    public function __construct(
        public ?RegularityGoalProgress $current,
        public array $history,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->current && [] === $this->history;
    }
}
