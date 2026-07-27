<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\MuscleGroup;
use App\Service\Entity\MuscleGroupSorterService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MuscleGroupSorterServiceTest extends TestCase
{
    public function testMuscleGroupsAreSortedByTheirTranslatedName(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['muscle.quadriceps', [], 'muscle', null, 'Zzz traduit'],
            ['muscle.biceps', [], 'muscle', null, 'Aaa traduit'],
        ]);

        $quadriceps = $this->createMuscleGroup('muscle.quadriceps');
        $biceps = $this->createMuscleGroup('muscle.biceps');

        $service = new MuscleGroupSorterService($translator);
        $sorted = $service->sortByName([$quadriceps, $biceps], 'fr');

        self::assertSame('muscle.biceps', $sorted[0]->name);
        self::assertSame('muscle.quadriceps', $sorted[1]->name);
    }

    private function createMuscleGroup(string $name): MuscleGroup
    {
        $muscleGroup = new MuscleGroup();
        $muscleGroup->name = $name;

        return $muscleGroup;
    }
}
