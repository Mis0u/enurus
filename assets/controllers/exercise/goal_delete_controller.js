import { Controller } from '@hotwired/stimulus';
import { confirmDeletion, sendDeleteRequest, showDeleteError } from '../../utils/delete_confirmation.js';
import { showSuccessToast } from '../../utils/toast.js';

/**
 * Stimulus name: exercise--goal-delete
 *
 * Required data attributes:
 *   data-exercise--goal-delete-url-value           : DELETE endpoint URL
 *   data-exercise--goal-delete-csrf-token-value     : CSRF token for this goal
 *   data-exercise--goal-delete-confirm-title-value  : SweetAlert2 title (translated)
 *   data-exercise--goal-delete-confirm-text-value   : SweetAlert2 body text (translated)
 *   data-exercise--goal-delete-confirm-button-value : SweetAlert2 confirm button label (translated)
 *   data-exercise--goal-delete-cancel-button-value  : SweetAlert2 cancel button label (translated)
 *   data-exercise--goal-delete-error-text-value     : Error message on failure (translated)
 */
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        confirmTitle: String,
        confirmText: String,
        confirmButton: String,
        cancelButton: String,
        errorText: String,
    };

    async confirmDelete() {
        const confirmed = await confirmDeletion({
            title: this.confirmTitleValue,
            text: this.confirmTextValue,
            confirmButtonText: this.confirmButtonValue,
            cancelButtonText: this.cancelButtonValue,
            background: '#111827',
            color: '#f1f5f9',
            cancelButtonColor: 'transparent',
            customClass: { cancelButton: 'swal-cancel-btn' },
            reverseButtons: true,
        });

        if (!confirmed) {
            return;
        }

        const { ok, data } = await sendDeleteRequest(this.urlValue, this.csrfTokenValue);

        if (!ok || !data?.success) {
            showDeleteError({ text: this.errorTextValue, background: '#111827', color: '#f1f5f9' });
            return;
        }

        showSuccessToast(data.message);
        window.setTimeout(() => window.location.reload(), 1000);
    }
}
