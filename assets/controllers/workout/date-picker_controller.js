// assets/controllers/workout/date-picker_controller.js
//
// Calendrier stylé (flatpickr) pour la date d'une séance, à la place du calendrier natif du
// navigateur — celui-ci ne permet pas de différencier visuellement (curseur, couleurs) les
// dates sélectionnables des dates futures désactivées. Le format transmis au serveur
// (yyyy-MM-dd) reste identique à l'input natif, donc aucune conséquence côté validation.

import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { French } from 'flatpickr/dist/l10n/fr.js';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import { Italian } from 'flatpickr/dist/l10n/it.js';
import { German } from 'flatpickr/dist/l10n/de.js';
import { Dutch } from 'flatpickr/dist/l10n/nl.js';
import { Polish } from 'flatpickr/dist/l10n/pl.js';
import { Portuguese } from 'flatpickr/dist/l10n/pt.js';

const LOCALES = {
    fr: French,
    es: Spanish,
    it: Italian,
    de: German,
    nl: Dutch,
    pl: Polish,
    pt: Portuguese,
};

export default class extends Controller {
    static values = { locale: String };

    #flatpickr = null;

    connect() {
        this.#flatpickr = flatpickr(this.element, {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            locale: LOCALES[this.localeValue] ?? 'default',
        });
    }

    disconnect() {
        this.#flatpickr?.destroy();
    }
}
