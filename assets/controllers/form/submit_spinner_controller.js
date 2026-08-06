// assets/controllers/form/submit_spinner_controller.js
//
// Affiche un spinner sur le bouton submit dès la soumission native du formulaire parent.
// Pas de fetch ici : la page navigue (POST classique), donc pas besoin de masquer le spinner
// ensuite — soit la page suivante s'affiche, soit le formulaire est réaffiché avec des erreurs
// et le DOM (donc le bouton) est reconstruit depuis zéro.

import { Controller } from '@hotwired/stimulus';
import { showSpinner } from '../../utils/submit_spinner.js';

export default class extends Controller {
    #form = null;

    connect() {
        this.#form = this.element.closest('form');
        this.#form?.addEventListener('submit', this.#showSpinner);
    }

    disconnect() {
        this.#form?.removeEventListener('submit', this.#showSpinner);
    }

    #showSpinner = () => {
        showSpinner(this.element);
    };
}
