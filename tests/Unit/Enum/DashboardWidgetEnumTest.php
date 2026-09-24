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
}
