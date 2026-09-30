<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * Lecture typée du JSON d'un snapshot figé : toute donnée inattendue lève une `LogicException`
 * plutôt que de produire un résumé faux en silence.
 */
final class SnapshotArrayReader
{
    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (! \is_int($value)) {
            throw new \LogicException(\sprintf('Year in review snapshot: "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * JSON ne distingue pas `4200.0` de `4200` : un entier relu est accepté comme décimal.
     *
     * @param array<mixed> $data
     */
    public static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (! \is_int($value) && ! \is_float($value)) {
            throw new \LogicException(\sprintf('Year in review snapshot: "%s" must be a number.', $key));
        }

        return (float) $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! \is_string($value)) {
            throw new \LogicException(\sprintf('Year in review snapshot: "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (! \is_bool($value)) {
            throw new \LogicException(\sprintf('Year in review snapshot: "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! \is_array($value)) {
            throw new \LogicException(\sprintf('Year in review snapshot: "%s" must be an array.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    public static function nullableArray(array $data, string $key): ?array
    {
        return null === ($data[$key] ?? null) ? null : self::array($data, $key);
    }

    /**
     * @param array<mixed> $items
     *
     * @return list<array<mixed>>
     */
    public static function listOfArrays(array $items): array
    {
        return array_values(array_map(
            static fn (mixed $item): array => \is_array($item)
                ? $item
                : throw new \LogicException('Year in review snapshot: list items must be arrays.'),
            $items,
        ));
    }
}
