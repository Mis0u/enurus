import { Controller } from '@hotwired/stimulus';

// Croix du bandeau « Ton année est prête » du dashboard : enregistre le choix côté serveur, puis
// retire le bandeau. En cas d'échec (réseau, serveur), le bandeau reste : rien n'a été enregistré.
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
    };

    async dismiss() {
        if (await this.#save()) {
            this.element.remove();
        }
    }

    async #save() {
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': this.csrfTokenValue },
            });

            return response.ok;
        } catch {
            return false;
        }
    }
}
