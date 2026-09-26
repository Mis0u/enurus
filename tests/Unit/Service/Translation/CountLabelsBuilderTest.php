<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Translation;

use App\Service\Translation\CountLabelsBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CountLabelsBuilderTest extends TestCase
{
    public function testBuildsOneTranslatedLabelPerCountFromZeroToTheMaximum(): void
    {
        $builder = new CountLabelsBuilder($this->translatorWritingCount());

        self::assertSame(['0 items', '1 items', '2 items'], $builder->build('list.count', 'navigation', 2));
    }

    public function testAnEmptyListStillGetsTheZeroLabel(): void
    {
        $builder = new CountLabelsBuilder($this->translatorWritingCount());

        self::assertSame(['0 items'], $builder->build('list.count', 'navigation', 0));
    }

    private function translatorWritingCount(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters): string => \sprintf('%d items', $parameters['count']),
        );

        return $translator;
    }
}
