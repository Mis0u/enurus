<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ExerciseRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExerciseCheckDuplicateService
{
    public function __construct(
        private ExerciseRepository $exerciseRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Returns a result array describing the duplicate type, or ['type' => null] if none.
     *
     * @return array{type: 'custom'|'public'|null, name?: string, date?: string}
     */
    public function check(string $submittedName, User $user, string $locale): array
    {
        $normalized = $this->normalize($submittedName);
        $dateFormat = $this->getDateFormat($locale);

        $customResult = $this->checkCustomDuplicate($normalized, $user, $dateFormat);

        if (null !== $customResult) {
            return $customResult;
        }

        $publicResult = $this->checkPublicDuplicate($normalized, $locale);

        if (null !== $publicResult) {
            return $publicResult;
        }

        return [
            'type' => null,
        ];
    }

    /**
     * @return array{type: 'custom', name: string, date: string}|null
     */
    private function checkCustomDuplicate(string $normalized, User $user, string $dateFormat): ?array
    {
        $exercises = $this->exerciseRepository->findCustomExercisesByUser($user);

        foreach ($exercises as $exercise) {
            if ($this->normalize($exercise->name) !== $normalized) {
                continue;
            }

            return [
                'type' => 'custom',
                'name' => $exercise->name,
                'date' => $exercise->createdAt->format($dateFormat),
            ];
        }

        return null;
    }

    /**
     * @return array{type: 'public', name: string}|null
     */
    private function checkPublicDuplicate(string $normalized, string $locale): ?array
    {
        $exercises = $this->exerciseRepository->findPublicExercises();

        foreach ($exercises as $exercise) {
            $translated = $this->translator->trans(
                $exercise->name,
                domain: 'exercise',
                locale: $locale,
            );

            if ($this->normalize($translated) !== $normalized) {
                continue;
            }

            return [
                'type' => 'public',
                'name' => $translated,
            ];
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function getDateFormat(string $locale): string
    {
        return match ($locale) {
            LocaleAllowedEnum::EN->value => 'm/d/Y',
            LocaleAllowedEnum::DE->value => 'd.m.Y',
            default => 'd/m/Y',
        };
    }
}
