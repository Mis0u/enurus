<?php

declare(strict_types=1);

namespace App\Service\Utils;

/**
 * Ré-encode une image dans son format d'origine : seuls les pixels sont conservés, jamais les
 * métadonnées (EXIF dont la position GPS d'une photo prise au téléphone, chunks texte PNG) ni un
 * éventuel contenu caché à la suite des données d'image. Les fichiers stockés étant publics,
 * aucune métadonnée ne doit y survivre.
 *
 * L'orientation EXIF disparaissant avec le reste, elle est appliquée aux pixels avant l'encodage :
 * sans ça, une photo prise en portrait s'afficherait couchée.
 */
final readonly class ImageMetadataStripper
{
    private const int LOSSY_QUALITY = 90;

    /**
     * Angles de rotation GD (sens anti-horaire) par valeur du tag EXIF Orientation. Les variantes
     * en miroir (2, 4, 5, 7), qu'aucun téléphone ne produit en pratique, restent telles quelles.
     */
    private const array ROTATION_BY_EXIF_ORIENTATION = [
        3 => 180,
        6 => 270,
        8 => 90,
    ];

    /**
     * @return resource flux positionné au début, contenant l'image nettoyée
     */
    public function strip(string $path)
    {
        $type = $this->detectType($path);
        $image = imagecreatefromstring(file_get_contents($path) ?: '')
            ?: throw new \InvalidArgumentException('The file is not a readable image.');

        if (IMAGETYPE_JPEG === $type) {
            $image = $this->applyExifOrientation($image, $path);
        }

        return $this->encode($image, $type);
    }

    private function detectType(string $path): int
    {
        $type = getimagesize($path)[2] ?? null;

        return \in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            ? $type
            : throw new \InvalidArgumentException('Only JPEG, PNG and WebP images are supported.');
    }

    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        $exif = @exif_read_data($path);
        $orientation = \is_array($exif) ? ($exif['Orientation'] ?? null) : null;
        $angle = self::ROTATION_BY_EXIF_ORIENTATION[$orientation] ?? null;

        if (null === $angle) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }

    /**
     * @return resource
     */
    private function encode(\GdImage $image, int $type)
    {
        $stream = fopen('php://temp', 'w+b') ?: throw new \LogicException('Unable to open a temporary stream.');

        if (IMAGETYPE_JPEG !== $type) {
            imagesavealpha($image, true);
        }

        $encoded = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $stream, self::LOSSY_QUALITY),
            IMAGETYPE_PNG => imagepng($image, $stream),
            default => imagewebp($image, $stream, self::LOSSY_QUALITY),
        };

        if (! $encoded) {
            throw new \LogicException('Unable to encode the image.');
        }

        rewind($stream);

        return $stream;
    }
}
