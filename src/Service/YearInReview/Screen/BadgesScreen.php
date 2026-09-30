<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;
use App\Service\Badge\View\BadgeTileView;

/**
 * Au-delà de neuf badges, les suivants sont seulement comptés (« +N ») : l'écran doit tenir sur un
 * téléphone sans défiler.
 */
final readonly class BadgesScreen implements YearInReviewScreen
{
    public const int VISIBLE_BADGE_LIMIT = 9;

    public function __construct(
        /**
         * @var list<BadgeTileView>
         */
        public array $badges,
    ) {
    }

    /**
     * @return list<BadgeTileView>
     */
    public function visibleBadges(): array
    {
        return \array_slice($this->badges, 0, self::VISIBLE_BADGE_LIMIT);
    }

    public function hiddenBadgeCount(): int
    {
        return max(0, \count($this->badges) - self::VISIBLE_BADGE_LIMIT);
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::BADGES;
    }
}
