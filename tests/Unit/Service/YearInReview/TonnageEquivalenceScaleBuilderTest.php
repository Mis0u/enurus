<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\TonnageEquivalenceEnum;
use App\Service\YearInReview\TonnageEquivalenceScaleBuilder;
use App\Service\YearInReview\View\TonnageEquivalenceRow;
use PHPUnit\Framework\TestCase;

final class TonnageEquivalenceScaleBuilderTest extends TestCase
{
    public function testEveryObjectOfTheScaleWithItsWeightInKilograms(): void
    {
        $rows = new TonnageEquivalenceScaleBuilder()->rows(UnitOfMeasureEnum::KG);

        self::assertCount(\count(TonnageEquivalenceEnum::cases()), $rows);
        self::assertEquals(new TonnageEquivalenceRow(TonnageEquivalenceEnum::GRAND_PIANO, 480, UnitOfMeasureEnum::KG), $rows[0]);
    }

    public function testWeightsAreConvertedToPoundsForAPoundsReader(): void
    {
        $rows = new TonnageEquivalenceScaleBuilder()->rows(UnitOfMeasureEnum::LBS);

        self::assertEquals(new TonnageEquivalenceRow(TonnageEquivalenceEnum::GRAND_PIANO, 1058, UnitOfMeasureEnum::LBS), $rows[0]);
    }
}
