<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use function Castor\io;
use function Castor\run;

// ============================================
// CODE QUALITY
// ============================================
#[AsTask(description: 'Static analysis with PHPStan')]
function phpstan(): void
{
    execute(
        'PHPSTAN',
        '🔍 Running static analysis...',
        'vendor/bin/phpstan analyse -c tools/phpstan/phpstan.dist.neon',
        'Static analysis passed'
    );
}

#[AsTask(description: 'Check code style with ECS')]
function ecs(): void
{
    execute(
        'ECS',
        '📏 Checking code style...',
        'vendor/bin/ecs check --config tools/ecs/ecs.php',
        'Code style passed'
    );
}

#[AsTask(description: 'Fix code style with ECS')]
function ecsFix(): void
{
    execute(
        'ECS FIX',
        '🎨 Fixing code style...',
        'vendor/bin/ecs check --config tools/ecs/ecs.php --fix',
        'Code style fixed'
    );
}

#[AsTask(description: 'Detect magic numbers with PHPMND')]
function phpmnd(): void
{
    execute(
        'PHPMND',
        '🔢 Checking magic numbers...',
        'vendor/bin/phpmnd src',
        'Magic numbers check passed'
    );
}

#[AsTask(description: 'Lint Twig templates with TwigCS')]
function twigcs(): void
{
    execute(
        'TWIGCS',
        '🎭 Linting Twig templates...',
        'vendor/bin/twigcs templates',
        'Twig linting passed'
    );
}

#[AsTask(description: 'Execute test with phpunit')]
function test(): void
{
    execute(
        'PHPUNIT',
        '✅ Running phpunit tests...',
        './vendor/bin/phpunit tests',
        'All the tests passed'
    );
}

function execute(string $title, string $section, string $command, string $success): void
{
    io()->title($title);
    io()->section($section);
    run($command);
    io()->success($success);
}

#[AsTask(description: 'Run all quality checks')]
function qa(): void
{
    test();
    phpstan();
    ecs();
    phpmnd();
    twigcs();
    io()->success('✨ All checks passed! Ready to commit.');
}
