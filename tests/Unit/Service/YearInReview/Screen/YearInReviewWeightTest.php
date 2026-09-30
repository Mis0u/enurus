<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Screen;

use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\YearInReviewWeightUnitEnum;
use App\Service\YearInReview\Screen\YearInReviewWeight;
use PHPUnit\Framework\TestCase;

final class YearInReviewWeightTest extends TestCase
{
    public function testYearTonnageIsInWholeTonnesForAKilogramReader(): void
    {
        self::assertEquals(new YearInReviewWeight(1293.0, YearInReviewWeightUnitEnum::TONNE), YearInReviewWeight::tonnage(1292934.4, UnitOfMeasureEnum::KG));
    }

    public function testYearTonnageIsInWholePoundsForAPoundReader(): void
    {
        self::assertEquals(new YearInReviewWeight(2205.0, YearInReviewWeightUnitEnum::POUND), YearInReviewWeight::tonnage(1000.0, UnitOfMeasureEnum::LBS));
    }

    public function testReferenceWeightStaysInKilogramsBelowOneTonne(): void
    {
        self::assertEquals(new YearInReviewWeight(480.0, YearInReviewWeightUnitEnum::KILOGRAM), YearInReviewWeight::reference(480.0, UnitOfMeasureEnum::KG));
    }

    public function testReferenceWeightSwitchesToTonnesWithOneDecimalFromOneTonne(): void
    {
        self::assertEquals(new YearInReviewWeight(13.7, YearInReviewWeightUnitEnum::TONNE), YearInReviewWeight::reference(13700.0, UnitOfMeasureEnum::KG));
    }

    public function testLiftedWeightKeepsOneDecimalInTheReaderUnit(): void
    {
        self::assertEquals(new YearInReviewWeight(172.5, YearInReviewWeightUnitEnum::KILOGRAM), YearInReviewWeight::lifted(172.5, UnitOfMeasureEnum::KG));
        self::assertEquals(new YearInReviewWeight(380.3, YearInReviewWeightUnitEnum::POUND), YearInReviewWeight::lifted(172.5, UnitOfMeasureEnum::LBS));
    }
}
