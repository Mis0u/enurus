// assets/controllers/form/submit_spinner_controller.js
//
// Affiche un spinner sur le bouton submit dès la soumission native du formulaire parent.
// Pas de fetch ici : la page navigue (POST classique), donc pas besoin de masquer le spinner
// ensuite — soit la page suivante s'affiche, soit le formulaire est réaffiché avec des erreurs
// et le DOM (donc le bouton) est reconstruit depuis zéro.

import { Controller } from '@hotwired/stimulus';

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
        this.element.disabled = true;
        this.element.classList.add('cursor-wait');

        const spinner = document.createElement('span');
        spinner.className = 'inline-block w-4 h-4 ml-2 align-middle border-2 border-white/30 border-t-white rounded-full animate-spin';
        spinner.setAttribute('aria-hidden', 'true');
        this.element.appendChild(spinner);
    };
}
