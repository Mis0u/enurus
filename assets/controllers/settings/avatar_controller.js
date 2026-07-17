import { Controller } from '@hotwired/stimulus';
import { sendDeleteRequest } from '../../utils/delete_confirmation.js';

export default class extends Controller {
    static targets = ['input', 'image', 'initials', 'spinner', 'removeButton', 'error', 'errorText'];
    static values = {
        uploadUrl: String,
        deleteUrl: String,
        deleteCsrfToken: String,
        maxSize: Number,
        allowedTypes: Array,
        tooLargeError: String,
        invalidTypeError: String,
    };

    connect() {
        this.boundUpdateInitials = this.#updateInitials.bind(this);
        window.addEventListener('user:nickname-updated', this.boundUpdateInitials);
    }

    disconnect() {
        window.removeEventListener('user:nickname-updated', this.boundUpdateInitials);
    }

    #updateInitials(event) {
        this.initialsTarget.textContent = event.detail.initials;
    }

    async upload() {
        const file = this.inputTarget.files[0];

        if (!file) {
            return;
        }

        const violation = this.#validate(file);

        if (violation) {
            this.#showError(violation);
            this.inputTarget.value = '';
            return;
        }

        this.#hideError();
        this.spinnerTarget.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('avatar', file);

            const response = await fetch(this.uploadUrlValue, { method: 'POST', body: formData });

            if (!response.ok) {
                this.#showError(this.tooLargeErrorValue);
                return;
            }

            const data = await response.json();
            this.#applyImage(data.url);
            this.#broadcastAvatarUpdate(data.url);
        } catch {
            this.#showError(this.tooLargeErrorValue);
        } finally {
            this.spinnerTarget.classList.add('hidden');
        }
    }

    async remove() {
        const { ok, data } = await sendDeleteRequest(this.deleteUrlValue, this.deleteCsrfTokenValue);

        if (ok && data?.success) {
            this.#applyInitials();
            this.#broadcastAvatarUpdate(null);
        }
    }

    #validate(file) {
        if (!this.allowedTypesValue.includes(file.type)) {
            return this.invalidTypeErrorValue;
        }

        if (file.size > this.maxSizeValue) {
            return this.tooLargeErrorValue;
        }

        return null;
    }

    #applyImage(url) {
        this.imageTarget.src = url;
        this.imageTarget.classList.remove('hidden');
        this.initialsTarget.classList.add('hidden');
        this.removeButtonTarget.classList.remove('hidden!');
    }

    #applyInitials() {
        this.imageTarget.classList.add('hidden');
        this.initialsTarget.classList.remove('hidden');
        this.removeButtonTarget.classList.add('hidden!');
        this.inputTarget.value = '';
    }

    #broadcastAvatarUpdate(url) {
        window.dispatchEvent(new CustomEvent('user:avatar-updated', {
            detail: { url },
        }));
    }

    #showError(message) {
        this.errorTextTarget.textContent = message;
        this.errorTarget.classList.remove('hidden');
    }

    #hideError() {
        this.errorTarget.classList.add('hidden');
    }
}
