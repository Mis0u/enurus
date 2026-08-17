import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['select'];
    static values = {
        url: String,
        csrfToken: String,
        paramName: String,
        successMessage: String,
    };

    save() {
        this.#persist(this.selectTarget.value);
    }

    choose(event) {
        const value = event.params.value;

        this.element.querySelectorAll('[data-settings--select-field-option]').forEach((btn) => {
            btn.classList.toggle('bg-[#f43f5e]', btn === event.currentTarget);
            btn.classList.toggle('text-white', btn === event.currentTarget);
            btn.classList.toggle('text-[#4a5568]', btn !== event.currentTarget);
        });

        this.#persist(value);
    }

    async #persist(value) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ [this.paramNameValue]: value, _token: this.csrfTokenValue }),
            });

            if (response.ok) {
                showSuccessToast(this.successMessageValue);
                window.dispatchEvent(new CustomEvent('settings:field-updated', {
                    detail: { paramName: this.paramNameValue, value },
                }));
            }
        } catch {
            // Échec silencieux volontaire — le select/toggle garde la valeur choisie,
            // l'absence de toast signale l'échec à l'utilisateur.
        }
    }
}
