<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Dashboard\DashboardWidgetEnum;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetEnumTest extends TestCase
{
    public function testConnectionsWidgetIsNeverShareable(): void
    {
        self::assertFalse(DashboardWidgetEnum::CONNECTIONS->isShareable());
    }

    public function testEveryOtherWidgetIsShareable(): void
    {
        foreach (DashboardWidgetEnum::cases() as $widget) {
            if (DashboardWidgetEnum::CONNECTIONS !== $widget) {
                self::assertTrue($widget->isShareable(), $widget->value . ' should be shareable');
            }
        }
    }

    /**
     * Ordre par défaut = celui du dashboard avant la réorganisation (grille historique).
     */
    public function testWithoutSavedOrderWidgetsFollowTheDefaultDashboardOrder(): void
    {
        self::assertSame(
            ['session', 'tonnage', 'muscle_distribution', 'regularity', 'regularity_goal', 'comparison', 'goals', 'heatmap', 'connections', 'badges'],
            $this->keys(DashboardWidgetEnum::inOrder([])),
        );
    }

    public function testSavedOrderComesFirst(): void
    {
        $ordered = $this->keys(DashboardWidgetEnum::inOrder(['badges', 'session']));

        self::assertSame(['badges', 'session'], \array_slice($ordered, 0, 2));
    }

    /**
     * Un widget débloqué après une réorganisation (ou ajouté dans une future version) n'est pas dans
     * l'ordre enregistré : il arrive à la fin, dans l'ordre par défaut.
     */
    public function testWidgetsMissingFromTheSavedOrderAreAppendedInDefaultOrder(): void
    {
        self::assertSame(
            ['badges', 'session', 'tonnage', 'muscle_distribution', 'regularity', 'regularity_goal', 'comparison', 'goals', 'heatmap', 'connections'],
            $this->keys(DashboardWidgetEnum::inOrder(['badges', 'session'])),
        );
    }

    public function testUnknownAndDuplicatedKeysAreIgnored(): void
    {
        $ordered = $this->keys(DashboardWidgetEnum::inOrder(['removed_widget', 'goals', 'goals']));

        self::assertSame('goals', $ordered[0]);
        self::assertCount(\count(DashboardWidgetEnum::cases()), $ordered);
    }

    /**
     * @param list<DashboardWidgetEnum> $widgets
     * @return list<string>
     */
    private function keys(array $widgets): array
    {
        return array_map(static fn (DashboardWidgetEnum $widget): string => $widget->value, $widgets);
    }
}
