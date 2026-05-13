<?php

declare(strict_types=1);

namespace App\DataFixtures\Service\File;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadJsonFile(string $fileName): array
    {
        $path = \sprintf('%s/JSON/%s', dirname(__DIR__, 2), $fileName);

        if (! file_exists($path)) {
            throw new \RuntimeException(\sprintf('Fichier introuvable : %s', $path));
        }

        $content = file_get_contents($path);

        if (false === $content) {
            throw new \RuntimeException(\sprintf('Impossible de lire le fichier : %s', $path));
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException(\sprintf('JSON invalide : %s', $e->getMessage()));
        }

        if (! is_array($data)) {
            throw new \RuntimeException(\sprintf('Le fichier JSON ne contient pas un tableau : %s', $path));
        }

        /** @var array<int, array<string, mixed>> $data */
        return $data;
    }
}
