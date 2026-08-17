import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['input', 'error'];
    static values = {
        url: String,
        csrfToken: String,
        min: Number,
        max: Number,
        minKg: Number,
        maxKg: Number,
        minLbs: Number,
        maxLbs: Number,
        rangeError: String,
        rangeErrorKg: String,
        rangeErrorLbs: String,
        invalidError: String,
        placeholderKg: String,
        placeholderLbs: String,
        successMessage: String,
    };

    connect() {
        this.boundHandler = this.#onUnitUpdated.bind(this);
        window.addEventListener('settings:field-updated', this.boundHandler);
    }

    disconnect() {
        window.removeEventListener('settings:field-updated', this.boundHandler);
    }

    save() {
        const value = this.inputTarget.value.trim();

        if ('' === value) {
            this.#hideError();
            this.#persist('');
            return;
        }

        const violation = this.#validate(value);

        if (violation) {
            this.#showError(violation);
            return;
        }

        this.#hideError();
        this.#persist(value);
    }

    #onUnitUpdated(event) {
        if ('unit' !== event.detail.paramName) return;

        const isKg = 'kg' === event.detail.value;

        this.minValue = isKg ? this.minKgValue : this.minLbsValue;
        this.maxValue = isKg ? this.maxKgValue : this.maxLbsValue;
        this.rangeErrorValue = isKg ? this.rangeErrorKgValue : this.rangeErrorLbsValue;

        this.inputTarget.min = this.minValue;
        this.inputTarget.max = this.maxValue;
        this.inputTarget.placeholder = isKg ? this.placeholderKgValue : this.placeholderLbsValue;

        this.#hideError();
    }

    #validate(value) {
        const numeric = Number(value);

        if (Number.isNaN(numeric)) {
            return this.invalidErrorValue;
        }

        if (numeric < this.minValue || numeric > this.maxValue) {
            return this.rangeErrorValue;
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

    async #persist(bodyweight) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bodyweight, _token: this.csrfTokenValue }),
            });

            if (!response.ok) {
                this.#showError(this.rangeErrorValue);
                return;
            }

            showSuccessToast(this.successMessageValue);
        } catch {
            this.#showError(this.rangeErrorValue);
        }
    }
}
