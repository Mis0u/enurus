import { Controller } from '@hotwired/stimulus';
import { confirmDeletion, sendDeleteRequest, showDeleteError } from '../../utils/delete_confirmation.js';

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
        const confirmed = await confirmDeletion({
            title:             this.confirmTitleValue,
            text:              this.confirmTextValue,
            confirmButtonText: this.confirmButtonValue,
            cancelButtonText:  this.cancelButtonValue,
            reverseButtons:    true,
        });

        if (!confirmed) { return; }

        const { ok, data } = await sendDeleteRequest(this.urlValue, this.csrfTokenValue);

        if (!ok || !data?.success) {
            showDeleteError({ title: this.errorTextValue });
            return;
        }

        window.location.href = this.redirectUrlValue;
    }
}
