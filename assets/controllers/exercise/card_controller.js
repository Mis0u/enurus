// Ligne compacte d'un exercice de la Bibliothèque (mobile) : un appui sur le nom déplie ou replie
// les pastilles de muscles et la description. Sur desktop, les détails restent toujours visibles
// (la règle qui les masque n'existe qu'en mobile, cf. exercise-list.css).

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle'];

    toggle() {
        const isExpanded = this.element.classList.toggle('is-expanded');

        this.toggleTarget.setAttribute('aria-expanded', String(isExpanded));
    }
}
