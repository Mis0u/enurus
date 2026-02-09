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
        'vendor/bin/phpmnd src --exclude-path=DataFixtures --extensions=operation',
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
function test(bool $coverage = false): void
{
    $coverageOption = $coverage ? '--coverage-html coverage' : '';
    execute(
        'PHPUNIT',
        '✅ Running phpunit tests...',
        \sprintf('./vendor/bin/phpunit tests --testdox %s', $coverageOption),
        'All the tests passed'
    );
}

#[AsTask(description: 'Execute Eslint')]
function eslint(): void
{
    execute(
        'ESLINT',
        '👮 Linting JavaScript...',
        'npx eslint assets/controllers',
        'Code style validated'
    );
}

#[AsTask(description: 'Run all quality checks')]
function qa(): void
{
    test();
    phpstan();
    ecs();
    phpmnd();
    twigcs();
    eslint();
    io()->success('✨ All checks passed! Ready to commit.');
}

// ============================================
// DB
// ============================================

#[AsTask(description: 'Launch the database with docker')]
function dbUp(): void
{
    execute(
        'DB',
        '🔝 DB MOUTING...',
        'docker compose -f docker/docker-compose.yml up -d',
        'DB is up'
    );
}

#[AsTask(description: 'Close the database with docker')]
function dbDown(): void
{
    execute(
        'DB',
        '⬇️ DB Closing...',
        'docker compose -f docker/docker-compose.yml up -d',
        'DB is down'
    );
}

#[AsTask(description: 'Doctrine create database')]
function createDb(bool $test = false): void
{
    [$env, $dbType] = getDatabaseContext($test);
    execute(
        \sprintf('CREATE %s', $dbType),
        \sprintf('⬇🏗️ %s creating...', $dbType),
        \sprintf('%sphp bin/console doctrine:database:create --if-not-exists', $env),
        \sprintf('%s is created', $dbType)
    );
}

#[AsTask(description: 'Doctrine delete database without question')]
function dropDb(bool $test = false): void
{
    [$env, $dbType] = getDatabaseContext($test);
    execute(
        \sprintf('DELETE %s', $dbType),
        \sprintf('💣 %s deleting...', $dbType),
        \sprintf('%sphp bin/console d:d:d --force --if-exists', $env),
        sprintf('%s is deleted', $dbType)
    );
}

#[AsTask(description: 'Doctrine make migrations')]
function makeMigration(): void
{
    execute(
        'MIGRATIONS',
        '⬇🔁 Migration\'s file creating...',
        'php bin/console make:migration',
        'Migration file is created'
    );
}

#[AsTask(description: 'Doctrine migrations migrate')]
function migrate(bool $test = false): void
{
    [$env, $dbType] = getDatabaseContext($test);
    execute(
        \sprintf('MIGRATING IN %s', $dbType),
        '⬇🔁 Migration\'s migrating...',
        \sprintf('%sphp bin/console d:m:m -n', $env),
        \sprintf('Migrations are migrated in %s', $dbType)
    );
}

#[AsTask(description: 'Doctrine run fixtures')]
function loadFixtures(bool $test = false): void
{
    [$env, $dbType] = getDatabaseContext($test);
    execute(
        \sprintf('FIXTURES LOADING IN %s', $dbType),
        '⬇🧪 Fixtures running...',
        \sprintf('%sphp bin/console doctrine:fixtures:load -n', $env),
        \sprintf('Fixtures are loaded in %s', $dbType)
    );
}

#[AsTask(description: 'Doctrine reset database (delete, create, migrate, fixtures')]
function resetDB(bool $test = false): void
{
    dropDb($test);
    createDb($test);
    migrate($test);
    loadFixtures($test);
}

// MESSENGER
#[AsTask(description: 'Consuming message for messenger')]
function consume(): void
{
    execute(
        'MESSAGE CONSUMPTION',
        '📨 Consuming messenger messages...',
        'php bin/console messenger:consume --all',
        'All messages are consumed'
    );
}

// TAILWIND

#[AsTask(description: 'Build tailwind assets')]
function tailwindBuild(): void
{
    execute(
        'TAILWIND BUILD',
        '🎨 Building Tailwind assets...',
        'php bin/console tailwind:build',
        'Tailwind assets have been built'
    );
}

function execute(string $title, string $section, string $command, string $success): void
{
    io()->title($title);
    io()->section($section);
    run($command);
    io()->success($success);
}

function getDatabaseContext(bool $test): array
{
    $env = $test ? 'APP_ENV=test ' : '';
    $dbLabel = $test ? 'TEST_DATABASE' : 'DATABASE';

    return [$env, $dbLabel];
}
