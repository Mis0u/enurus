<?php

declare(strict_types=1);

namespace App\Service\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Libellés traduits d'un compteur (« 0 exercice », « 1 exercice », « 2 exercices »…) pour chaque
 * valeur de 0 au maximum, transmis au JS qui met le compteur à jour sans recharger la page : le
 * pluriel ICU reste calculé côté serveur, exact dans toutes les langues (polonais compris), et
 * aucun texte n'est écrit en dur côté JS.
 */
final readonly class CountLabelsBuilder
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $key clé ICU recevant le paramètre `count`
     * @return list<string> index = valeur du compteur
     */
    public function build(string $key, string $domain, int $maxCount): array
    {
        return array_map(
            fn (int $count): string => $this->translator->trans($key, [
                'count' => $count,
            ], $domain),
            range(0, max(0, $maxCount)),
        );
    }
}
