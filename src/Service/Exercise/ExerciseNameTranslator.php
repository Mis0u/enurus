<?php

declare(strict_types=1);

namespace App\Service\Exercise;

use App\Entity\Exercise;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Nom affiché d'un exercice : clé de traduction (domaine `exercise`) pour un exercice public, nom
 * saisi tel quel pour un exercice perso.
 */
final readonly class ExerciseNameTranslator
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string|null $locale langue explicite, quand la requête n'est plus là pour la fournir
     *                            (contenu d'une réponse streamée, écrit après son traitement)
     */
    public function translate(Exercise $exercise, ?string $locale = null): string
    {
        return $exercise->isPublic ? $this->translator->trans($exercise->name, [], 'exercise', $locale) : $exercise->name;
    }
}
