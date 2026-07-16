<?php

declare(strict_types=1);

namespace App\Twig\Components\Button;

use App\Twig\Components\Trait\WithSvgIcon;

abstract class AbstractButton
{
    use WithSvgIcon;

    public string $message;

    public string $type = 'submit';

    public bool $disabled = false;

    public ?string $data = null;

    /**
     * Remplace entièrement getClasses() — pour un bouton dont le style n'a rien à voir avec la
     * variante canonique (ex. bouton compact en modale).
     */
    public ?string $class = null;

    /**
     * S'ajoute à getClasses() — pour un ajustement ponctuel (espacement, taille) qui garde le
     * style canonique de la variante.
     */
    public ?string $extraClass = null;

    abstract public function getClasses(): string;
}
