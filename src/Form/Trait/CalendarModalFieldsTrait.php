<?php

declare(strict_types=1);

namespace App\Form\Trait;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Style et sélecteur de date communs aux formulaires des modales de l'onglet Calendrier de Mes
 * séances (semaine de repos, objectif de régularité). La classe utilisatrice injecte `$requestStack`.
 *
 * @property RequestStack $requestStack
 */
trait CalendarModalFieldsTrait
{
    private const string FIELD_CLASS = 'w-full bg-white/[0.04] border border-white/[0.07] rounded-xl px-3.5 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none focus:border-[#06b6d4] focus:bg-white/[0.06] transition-all';

    private const string LABEL_CLASS = 'block text-[10.5px] font-semibold text-slate-500 mb-1.5';

    /**
     * @param bool $futureOnly aujourd'hui et après (début d'un objectif) ; sinon aujourd'hui et avant
     * @return array<string, mixed>
     */
    private function dateOptions(string $label, bool $futureOnly = false): array
    {
        return [
            ...$this->fieldOptions($label),
            'widget' => 'single_text',
            'html5' => false,
            'format' => 'yyyy-MM-dd',
            'input' => 'datetime_immutable',
            'attr' => [
                'class' => self::FIELD_CLASS,
                'data-controller' => 'workout--date-picker',
                'data-workout--date-picker-locale-value' => $this->currentLocale(),
                'data-workout--date-picker-future-only-value' => $futureOnly ? 'true' : 'false',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldOptions(string $label): array
    {
        return [
            'label' => $label,
            'translation_domain' => 'navigation',
            'attr' => [
                'class' => self::FIELD_CLASS,
            ],
            'label_attr' => [
                'class' => self::LABEL_CLASS,
            ],
        ];
    }

    private function currentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('Building a calendar form requires an active HTTP request.');
        }

        return $request->getLocale();
    }
}
