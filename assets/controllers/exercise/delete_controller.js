import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

/**
 * Exercise delete controller.
 *
 * Displays a SweetAlert2 confirmation, then sends a DELETE XHR request.
 * On success, removes the exercise card from the DOM.
 *
 * File: assets/controllers/exercise/delete_controller.js
 * Stimulus name: exercise--delete
 *
 * Required data attributes on the controller element:
 *   data-exercise--delete-url-value        : DELETE endpoint URL
 *   data-exercise--delete-csrf-token-value : CSRF token for this exercise
 *   data-exercise--delete-name-value       : exercise name (shown in confirmation)
 *   data-exercise--delete-confirm-title-value   : SweetAlert2 title (translated)
 *   data-exercise--delete-confirm-text-value    : SweetAlert2 body text (translated)
 *   data-exercise--delete-confirm-button-value  : SweetAlert2 confirm button label (translated)
 *   data-exercise--delete-cancel-button-value   : SweetAlert2 cancel button label (translated)
 *   data-exercise--delete-error-text-value      : Error message on failure (translated)
 */
export default class extends Controller {

    static values = {
        url:           String,
        csrfToken:     String,
        name:          String,
        confirmTitle:  String,
        confirmText:   String,
        confirmButton: String,
        cancelButton:  String,
        errorText:     String,
    };

    // ── Action ────────────────────────────────────────────────────────────────

    async confirmDelete() {
        const confirmed = await this.#showConfirmation();

        if (!confirmed) {
            return;
        }

        await this.#sendDeleteRequest();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    async #showConfirmation() {
        if (typeof Swal === 'undefined') {
            return window.confirm(this.confirmTextValue);
        }

        const result = await Swal.fire({
            title:              this.confirmTitleValue,
            text:               this.confirmTextValue,
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonText:  this.confirmButtonValue,
            cancelButtonText:   this.cancelButtonValue,
            background:         '#111827',
            color:              '#f1f5f9',
            confirmButtonColor: '#f43f5e',
            cancelButtonColor:  'transparent',
            customClass: {
                cancelButton: 'swal-cancel-btn',
            },
            reverseButtons: true,
        });

        return result.isConfirmed;
    }

    async #sendDeleteRequest() {
        try {
            const response = await fetch(this.urlValue, {
                method:  'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token':     this.csrfTokenValue,
                },
            });

            if (!response.ok) {
                this.#showError();
                return;
            }

            const data = await response.json();

            if (data.success) {
                this.#removeCard();
            } else {
                this.#showError();
            }
        } catch {
            this.#showError();
        }
    }

    #removeCard() {
        const card = this.element.closest('.exercise-history-card');
        card?.remove();
    }

    #showError() {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            icon:               'error',
            text:               this.errorTextValue,
            confirmButtonText:  'OK',
            background:         '#111827',
            color:              '#f1f5f9',
            confirmButtonColor: '#f43f5e',
        });
    }
}
