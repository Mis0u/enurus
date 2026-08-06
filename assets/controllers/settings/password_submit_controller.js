import { Controller } from '@hotwired/stimulus';
import { showSuccessToast, showErrorToast } from '../../utils/toast.js';
import { showSpinner, hideSpinner } from '../../utils/submit_spinner.js';

export default class extends Controller {
    static targets = ['submitButton'];

    static values = {
        url: String,
        successMessage: String,
        errorMessage: String,
    };

    async submit(event) {
        event.preventDefault();

        showSpinner(this.submitButtonTarget);

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
                this.submitButtonTarget.disabled = false;
                return;
            }

            showSuccessToast(this.successMessageValue);
            this.#resetForm();
        } catch {
            showErrorToast(this.errorMessageValue);
            this.submitButtonTarget.disabled = false;
        } finally {
            hideSpinner(this.submitButtonTarget);
        }
    }

    /**
     * `form.reset()` ne déclenche pas d'event `input` sur les champs — sans ce dispatch manuel,
     * `password-validator` (qui écoute `input` pour activer/désactiver le bouton submit) ne revoit
     * jamais la validation et laisse le bouton dans son état d'avant reset, malgré des champs
     * redevenus vides.
     */
    #resetForm() {
        this.element.reset();
        this.element.querySelectorAll('input[type="password"]').forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
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
