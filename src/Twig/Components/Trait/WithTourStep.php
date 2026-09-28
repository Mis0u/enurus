<?php

declare(strict_types=1);

namespace App\Twig\Components\Trait;

/**
 * Étape du tour guidé qui pointe cet élément (`data-tour-step`, lu par le controller Stimulus
 * `onboarding--tour`) — vide si l'élément n'est pas une cible du tour.
 */
trait WithTourStep
{
    public string $tourStep = '';
}
