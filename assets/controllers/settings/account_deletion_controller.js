import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        nickname: String,
        title: String,
        body: String,
        placeholder: String,
        mismatchError: String,
        confirmLabel: String,
        cancelLabel: String,
        requestError: String,
    };

    openModal() {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            title: this.titleValue,
            html: `<p style="margin-bottom: 12px;">${this.bodyValue}</p>`,
            input: 'text',
            inputPlaceholder: this.placeholderValue,
            background: '#0f1928',
            color: '#f0f4ff',
            confirmButtonText: this.confirmLabelValue,
            confirmButtonColor: '#f43f5e',
            cancelButtonText: this.cancelLabelValue,
            showCancelButton: true,
            focusConfirm: false,
            preConfirm: (value) => this.#validateNickname(value),
        }).then((result) => {
            if (result.isConfirmed) {
                this.#requestDeletion();
            }
        });
    }

    #validateNickname(value) {
        if (value !== this.nicknameValue) {
            Swal.showValidationMessage(this.mismatchErrorValue);
            return false;
        }

        return true;
    }

    async #requestDeletion() {
        try {
            const formData = new FormData();
            formData.append('_token', this.csrfTokenValue);

            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData,
            });

            if (!response.ok) {
                this.#showRequestError();
                return;
            }

            const data = await response.json();
            window.location.href = data.logoutUrl;
        } catch {
            this.#showRequestError();
        }
    }

    #showRequestError() {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            icon: 'error',
            text: this.requestErrorValue,
            background: '#0f1928',
            color: '#f0f4ff',
        });
    }
}
