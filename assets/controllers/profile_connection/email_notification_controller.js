import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

// Interrupteur « email à chaque demande de connexion » de la page Connexions : enregistré à la
// volée, remis dans son état précédent si le serveur refuse ou si le réseau échoue.
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        successMessage: String,
    };

    async toggle(event) {
        const checkbox = event.target;

        if (await this.#save(checkbox.checked)) {
            showSuccessToast(this.successMessageValue);

            return;
        }

        checkbox.checked = ! checkbox.checked;
    }

    async #save(enabled) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled, _token: this.csrfTokenValue }),
            });

            return response.ok;
        } catch {
            return false;
        }
    }
}
