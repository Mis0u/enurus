<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Badge;

use App\Entity\User;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Service\Badge\BadgeKey;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\BadgeProgress;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Avec les vraies traductions ICU : la moindre faute de pluriel ou de clé se verrait ici.
 */
final class BadgeLabelFormatterTest extends KernelTestCase
{
    public function testAssiduityNameAndRibbon(): void
    {
        $formatter = $this->formatter('fr');
        $key = new BadgeKey(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE);

        self::assertSame('1 séance', $formatter->name($key, $this->user()));
        self::assertSame('1', $formatter->ribbon($key, $this->user()));
    }

    public function testSeniorityBelowOneYearIsInMonthsThenInYears(): void
    {
        $formatter = $this->formatter('fr');

        self::assertSame('6 mois', $formatter->name(new BadgeKey(BadgeFamilyEnum::SENIORITY, BadgeTierEnum::SILVER), $this->user()));
        self::assertSame('1 an', $formatter->name(new BadgeKey(BadgeFamilyEnum::SENIORITY, BadgeTierEnum::GOLD), $this->user()));
        self::assertSame('5 ANS', $formatter->ribbon(new BadgeKey(BadgeFamilyEnum::SENIORITY, BadgeTierEnum::RUBY), $this->user()));
    }

    public function testTonnageIsConvertedForLbsUsers(): void
    {
        $formatter = $this->formatter('en');
        $key = new BadgeKey(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::BRONZE);
        $lbsUser = $this->user(UnitOfMeasureEnum::LBS);

        self::assertSame('10 tonnes', $formatter->name($key, $this->user()));
        self::assertSame('22,046 lb', $formatter->name($key, $lbsUser));
        self::assertSame('22K LB', $formatter->ribbon($key, $lbsUser));
        self::assertSame('2.2M LB', $formatter->ribbon(new BadgeKey(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::PLATINUM), $lbsUser));
    }

    public function testProgressUsesTheViewerUnit(): void
    {
        $formatter = $this->formatter('en');
        $progress = new BadgeProgress(73, 14, 9, 142_000.0);

        self::assertSame('73 / 100 workouts', $formatter->progress(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::PLATINUM, $progress, $this->user()));
        self::assertSame('142 / 500 t', $formatter->progress(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::GOLD, $progress, $this->user()));
        self::assertSame('313,056 / 1,102,310 lb', $formatter->progress(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::GOLD, $progress, $this->user(UnitOfMeasureEnum::LBS)));
    }

    public function testPolishPluralForms(): void
    {
        $formatter = $this->formatter('pl');

        self::assertSame('1 trening', $formatter->name(new BadgeKey(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE), $this->user()));
        self::assertSame('50 treningów', $formatter->name(new BadgeKey(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::GOLD), $this->user()));
        self::assertSame('2 lata', $formatter->name(new BadgeKey(BadgeFamilyEnum::SENIORITY, BadgeTierEnum::PLATINUM), $this->user()));
    }

    public function testLegend(): void
    {
        $formatter = $this->formatter('fr');

        self::assertSame('Légende Enurus', $formatter->name(BadgeKey::legend(), $this->user()));
        self::assertSame('LÉGENDE', $formatter->ribbon(BadgeKey::legend(), $this->user()));
    }

    private function formatter(string $locale): BadgeLabelFormatter
    {
        self::bootKernel();

        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = static::getContainer()->get('translation.locale_switcher');
        $localeSwitcher->setLocale($locale);

        /** @var BadgeLabelFormatter $formatter */
        $formatter = static::getContainer()->get(BadgeLabelFormatter::class);

        return $formatter;
    }

    private function user(UnitOfMeasureEnum $unit = UnitOfMeasureEnum::KG): User
    {
        $user = new User();
        $user->unitOfMeasure = $unit;

        return $user;
    }
}
