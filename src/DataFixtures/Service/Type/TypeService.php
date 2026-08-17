<?php

declare(strict_types=1);

namespace App\DataFixtures\Service\Type;

class TypeService
{
    /**
     * @param array<string, mixed> $data
     */
    public function getString(array $data, string $field): string
    {
        if (! isset($data[$field]) || ! is_string($data[$field])) {
            throw new \UnexpectedValueException(
                sprintf('Le champ "%s" doit être une chaîne, "%s" reçu.', $field, gettype($data[$field] ?? null))
            );
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function getInt(array $data, string $field): int
    {
        if (! isset($data[$field]) || ! is_int($data[$field])) {
            throw new \UnexpectedValueException(
                sprintf('Le champ "%s" doit être un entier, "%s" reçu.', $field, gettype($data[$field] ?? null))
            );
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function getFloat(array $data, string $field): float
    {
        if (! isset($data[$field]) || (! is_int($data[$field]) && ! is_float($data[$field]))) {
            throw new \UnexpectedValueException(
                sprintf('Le champ "%s" doit être un nombre, "%s" reçu.', $field, gettype($data[$field] ?? null))
            );
        }

        return (float) $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function getBool(array $data, string $field): bool
    {
        if (! isset($data[$field]) || ! is_bool($data[$field])) {
            throw new \UnexpectedValueException(
                sprintf('Le champ "%s" doit être un boolean, "%s" reçu.', $field, gettype($data[$field] ?? null))
            );
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    public function getStringArray(array $data, string $field): array
    {
        $value = $data[$field] ?? [];

        if (! is_array($value)) {
            throw new \UnexpectedValueException(
                sprintf('Le champ "%s" doit être un tableau, "%s" reçu.', $field, gettype($value))
            );
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new \UnexpectedValueException(
                    sprintf('Le champ "%s" doit contenir uniquement des chaînes, "%s" reçu.', $field, gettype($item))
                );
            }
        }

        /** @var array<int, string> $value */
        return $value;
    }
}
