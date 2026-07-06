import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static values = {
        url: String,
        successMessage: String,
    };

    async submit(event) {
        event.preventDefault();

        const formData = new FormData(this.element);

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            this.#clearErrors();

            if (!response.ok) {
                this.#displayErrors(data.errors ?? {});
                return;
            }

            showSuccessToast(this.successMessageValue);
            this.element.reset();
            this.submitButtonTarget?.setAttribute('disabled', 'disabled');
        } catch {
            // Échec silencieux volontaire
        }
    }

    #clearErrors() {
        this.element.querySelectorAll('[data-field-error]').forEach((el) => el.remove());
    }

    #displayErrors(errors) {
        Object.entries(errors).forEach(([field, messages]) => {
            const input = this.element.querySelector(`[name*="[${field}]"]`);

            if (!input) {
                return;
            }

            const error = document.createElement('p');
            error.dataset.fieldError = field;
            error.className = 'mt-1.5 text-xs text-[#f43f5e]';
            error.textContent = messages[0];
            input.insertAdjacentElement('afterend', error);
        });
    }
}
