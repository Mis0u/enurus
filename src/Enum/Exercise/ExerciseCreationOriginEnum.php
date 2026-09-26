<?php

declare(strict_types=1);

namespace App\Enum\Exercise;

/**
 * Page d'où l'utilisateur est parti créer un exercice (paramètre `returnTo` de la page de
 * création) — liste fermée : jamais d'URL libre, pour qu'un lien forgé ne puisse pas rediriger
 * hors de l'application après la création.
 */
enum ExerciseCreationOriginEnum: string
{
    case WORKOUT = 'workout';
}
