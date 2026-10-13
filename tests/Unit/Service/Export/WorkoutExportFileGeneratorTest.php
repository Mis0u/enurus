<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use App\Entity\User;
use App\Enum\Export\WorkoutExportFormatEnum;
use App\Repository\WorkoutExportRepository;
use App\Service\Export\WorkoutExportFileGenerator;
use App\Service\Export\WorkoutExportWriter;
use PHPUnit\Framework\TestCase;

final class WorkoutExportFileGeneratorTest extends TestCase
{
    private const string FAILURE_MESSAGE = 'Database gone';

    /**
     * @var list<string>
     */
    private array $generatedFiles = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->generatedFiles, is_file(...)));
    }

    public function testTheFileIsWrittenByTheWriterOfTheRequestedFormat(): void
    {
        $generator = $this->generator([$this->writer(WorkoutExportFormatEnum::CSV, 'csv content'), $this->writer(WorkoutExportFormatEnum::JSON, '{}')]);

        $path = $this->generatedFiles[] = $generator->generate(new User(), WorkoutExportFormatEnum::JSON, 'fr');

        self::assertSame('{}', file_get_contents($path));
    }

    /**
     * Un export qui échoue en route ne laisse rien derrière lui : ni fichier à moitié écrit envoyé
     * à l'utilisateur, ni fichier temporaire oublié sur le serveur.
     */
    public function testAFailingWriterLeavesNoTemporaryFileBehind(): void
    {
        $filesBefore = $this->temporaryExportFiles();
        $generator = $this->generator([$this->failingWriter()]);

        try {
            $generator->generate(new User(), WorkoutExportFormatEnum::CSV, 'fr');
            self::fail('The writer failure should have been rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame(self::FAILURE_MESSAGE, $exception->getMessage());
        }

        self::assertSame($filesBefore, $this->temporaryExportFiles());
    }

    /**
     * @param list<WorkoutExportWriter> $writers
     */
    private function generator(array $writers): WorkoutExportFileGenerator
    {
        $repository = $this->createStub(WorkoutExportRepository::class);
        $repository->method('iterateByUser')->willReturn([]);

        return new WorkoutExportFileGenerator($repository, $writers);
    }

    private function writer(WorkoutExportFormatEnum $format, string $content): WorkoutExportWriter
    {
        return new readonly class($format, $content) implements WorkoutExportWriter {
            public function __construct(
                private WorkoutExportFormatEnum $format,
                private string $content,
            ) {
            }

            public function format(): WorkoutExportFormatEnum
            {
                return $this->format;
            }

            public function write(User $user, iterable $workouts, $stream, string $locale): void
            {
                fwrite($stream, $this->content);
            }
        };
    }

    private function failingWriter(): WorkoutExportWriter
    {
        return new class(self::FAILURE_MESSAGE) implements WorkoutExportWriter {
            public function __construct(
                private readonly string $message,
            ) {
            }

            public function format(): WorkoutExportFormatEnum
            {
                return WorkoutExportFormatEnum::CSV;
            }

            public function write(User $user, iterable $workouts, $stream, string $locale): void
            {
                fwrite($stream, 'Date;Heure');

                throw new \RuntimeException($this->message);
            }
        };
    }

    /**
     * @return list<string>
     */
    private function temporaryExportFiles(): array
    {
        return glob(sys_get_temp_dir() . '/' . WorkoutExportFileGenerator::FILE_PREFIX . '*') ?: [];
    }
}
