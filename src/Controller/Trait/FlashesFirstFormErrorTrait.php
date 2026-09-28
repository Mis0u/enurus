<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\Form\FormInterface;

/**
 * Formulaires des modales de l'onglet Calendrier : la modale se ferme au rechargement, le message
 * flash doit donc dire quel champ corriger (première erreur de validation), pas seulement qu'il y en
 * a un.
 */
trait FlashesFirstFormErrorTrait
{
    /**
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function firstFormErrorMessage(FormInterface $form, string $fallbackMessage): string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return $fallbackMessage;
    }
}
