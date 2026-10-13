<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\User;
use App\Enum\Export\WorkoutExportFormatEnum;
use App\Repository\WorkoutExportRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Prépare le fichier d'export en entier, dans un fichier temporaire, avant tout envoi. Une erreur en
 * cours d'écriture donne ainsi une vraie erreur (fichier supprimé, exception relancée), jamais un
 * fichier coupé reçu comme s'il était complet — ce que faisait l'ancien envoi au fil de l'eau.
 */
final readonly class WorkoutExportFileGenerator
{
    public const string FILE_PREFIX = 'enurus-export-';

    /**
     * @param iterable<WorkoutExportWriter> $writers
     */
    public function __construct(
        private WorkoutExportRepository $workoutExportRepository,
        #[AutowireIterator(WorkoutExportWriter::class)]
        private iterable $writers,
    ) {
    }

    /**
     * @return string chemin du fichier complet, à supprimer par l'appelant après envoi
     */
    public function generate(User $user, WorkoutExportFormatEnum $format, string $locale): string
    {
        $writer = $this->writerFor($format);
        $path = tempnam(sys_get_temp_dir(), self::FILE_PREFIX) ?: throw new \RuntimeException('Unable to create the export file.');

        try {
            $this->writeInto($path, fn ($stream) => $writer->write($user, $this->workoutExportRepository->iterateByUser($user), $stream, $locale));
        } catch (\Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        return $path;
    }

    private function writerFor(WorkoutExportFormatEnum $format): WorkoutExportWriter
    {
        foreach ($this->writers as $writer) {
            if ($format === $writer->format()) {
                return $writer;
            }
        }

        throw new \LogicException(\sprintf('No export writer for the "%s" format.', $format->value));
    }

    /**
     * @param callable(resource): void $write
     */
    private function writeInto(string $path, callable $write): void
    {
        $stream = fopen($path, 'wb') ?: throw new \RuntimeException('Unable to open the export file.');

        try {
            $write($stream);
        } finally {
            fclose($stream);
        }
    }
}
