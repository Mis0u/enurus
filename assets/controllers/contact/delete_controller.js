import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

/**
 * Contact thread delete controller.
 *
 * Displays a SweetAlert2 confirmation, then sends a DELETE XHR request.
 * On success, redirects to the messagerie list (the thread is no longer visible for this user).
 *
 * Stimulus name: contact--delete
 */
export default class extends Controller {
    static values = {
        url:           String,
        csrfToken:     String,
        redirectUrl:   String,
        confirmTitle:  String,
        confirmText:   String,
        confirmButton: String,
        cancelButton:  String,
        errorText:     String,
    };

    async confirmDelete() {
        const confirmed = await this.#showConfirmation();
        if (!confirmed) { return; }
        await this.#sendDeleteRequest();
    }

    async #showConfirmation() {
        const result = await Swal.fire({
            title:              this.confirmTitleValue,
            text:               this.confirmTextValue,
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonText:  this.confirmButtonValue,
            cancelButtonText:   this.cancelButtonValue,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor:  '#334155',
            background:         '#0f1928',
            color:              '#f0f4ff',
            reverseButtons:     true,
        });

        return result.isConfirmed;
    }

    async #sendDeleteRequest() {
        let response;
        try {
            response = await fetch(this.urlValue, {
                method:  'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token':     this.csrfTokenValue,
                },
            });
        } catch {
            this.#showError();
            return;
        }
        if (!response.ok) {
            this.#showError();
            return;
        }
        const data = await response.json();
        if (data.success) {
            window.location.href = this.redirectUrlValue;
        } else {
            this.#showError();
        }
    }

    #showError() {
        Swal.fire({
            title:              this.errorTextValue,
            icon:               'error',
            background:         '#0f1928',
            color:              '#f0f4ff',
            confirmButtonColor: '#f43f5e',
        });
    }
}
