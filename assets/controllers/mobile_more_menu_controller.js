// Panneau « Plus » de la navigation mobile : <dialog> modal natif, qui gère seul Échap, le focus
// gardé à l'intérieur et le reste de la page rendu inerte. Le controller ouvre, ferme (bouton,
// appui à côté du panneau) et tient `aria-expanded` du bouton à jour.

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'button'];

    connect() {
        this.dialogTarget.addEventListener('close', this.#onClosed);
        document.addEventListener('turbo:before-cache', this.#closeBeforeCache);
    }

    disconnect() {
        this.dialogTarget.removeEventListener('close', this.#onClosed);
        document.removeEventListener('turbo:before-cache', this.#closeBeforeCache);
    }

    open() {
        this.dialogTarget.showModal();
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.dialogTarget.close();
    }

    // Un appui sur le fond (::backdrop) cible le <dialog> lui-même, jamais son contenu.
    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    // Fermé aussi par Échap ou le geste retour d'Android : l'événement `close` couvre tous les cas.
    #onClosed = () => {
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    };

    // Jamais de panneau ouvert dans la copie de page gardée en cache par Turbo (revenir en arrière
    // le montrerait ouvert, sans rien pour le fermer).
    #closeBeforeCache = () => {
        if (this.dialogTarget.open) {
            this.close();
        }
    };
}
