import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['input', 'error'];
    static values = {
        url: String,
        csrfToken: String,
        minLength: Number,
        maxLength: Number,
        minError: String,
        maxError: String,
        successMessage: String,
    };

    save() {
        const value = this.inputTarget.value.trim();
        const violation = this.#validate(value);

        if (violation) {
            this.#showError(violation);
            return;
        }

        this.#hideError();
        this.#persist(value);
    }

    #validate(value) {
        if (value.length < this.minLengthValue) {
            return this.minErrorValue;
        }

        if (value.length > this.maxLengthValue) {
            return this.maxErrorValue;
        }

        return null;
    }

    #showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('hidden');
    }

    #hideError() {
        this.errorTarget.classList.add('hidden');
    }

    async #persist(nickname) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nickname, _token: this.csrfTokenValue }),
            });

            if (!response.ok) {
                this.#showError(this.maxErrorValue);
                return;
            }

            showSuccessToast(this.successMessageValue);
            this.#broadcastInitialsUpdate(nickname);
        } catch {
            this.#showError(this.maxErrorValue);
        }
    }

    #broadcastInitialsUpdate(nickname) {
        window.dispatchEvent(new CustomEvent('user:nickname-updated', {
            detail: { initials: this.#computeInitials(nickname) },
        }));
    }

    #computeInitials(nickname) {
        const chars = Array.from(nickname.trim());

        if (chars.length === 0) {
            return '';
        }

        return `${chars[0]}${chars[chars.length - 1]}`.toUpperCase();
    }
}
