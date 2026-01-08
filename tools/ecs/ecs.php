<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/../../src',
        __DIR__ . '/../../tests',
    ])
    ->withRootFiles()
    ->withPreparedSets(
        psr12: true,
        common: true,
        strict: true,
        cleanCode: true,
    )
    ->withConfiguredRule(YodaStyleFixer::class, [
        'equal' => true,
        'identical' => true,
        'less_and_greater' => true,
    ])
    ->withSkip([
        __DIR__ . '/../../importmap.php',
        __DIR__ . '/../../src/Kernel.php',
        __DIR__ . '/../../tests/bootstrap.php',
    ]);
