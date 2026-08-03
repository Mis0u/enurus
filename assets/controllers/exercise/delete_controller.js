import { Controller } from '@hotwired/stimulus';
import { confirmDeletion, sendDeleteRequest, showDeleteError } from '../../utils/delete_confirmation.js';
import { showSuccessToast } from '../../utils/toast.js';

/**
 * Exercise delete controller.
 *
 * Displays a SweetAlert2 confirmation, then sends a DELETE XHR request.
 * On success, removes the exercise card from the DOM — unless the exercise was archived rather
 * than deleted (still used in a past workout), in which case the page is reloaded so the card
 * re-renders in its archived state (badge + restore action), immediately reachable via the
 * "Archivés" filter.
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
        const confirmed = await confirmDeletion({
            title:              this.confirmTitleValue,
            text:               this.confirmTextValue,
            confirmButtonText:  this.confirmButtonValue,
            cancelButtonText:   this.cancelButtonValue,
            background:         '#111827',
            color:              '#f1f5f9',
            cancelButtonColor:  'transparent',
            customClass:        { cancelButton: 'swal-cancel-btn' },
            reverseButtons:     true,
        });

        if (!confirmed) {
            return;
        }

        const { ok, data } = await sendDeleteRequest(this.urlValue, this.csrfTokenValue);

        if (!ok || !data?.success) {
            showDeleteError({
                text:       this.errorTextValue,
                background: '#111827',
                color:      '#f1f5f9',
            });
            return;
        }

        showSuccessToast(data.message);

        if (data.archived) {
            setTimeout(() => window.location.reload(), 1200);
            return;
        }

        this.#removeCard();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    #removeCard() {
        const card = this.element.closest('.exercise-list-card');
        card?.remove();
    }
}
