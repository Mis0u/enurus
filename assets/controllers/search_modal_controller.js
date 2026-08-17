import { Controller } from '@hotwired/stimulus';
import { confirmAction } from '../utils/delete_confirmation.js';
import { showErrorToast } from '../utils/toast.js';

export default class extends Controller {
    stop(event) {
        event.stopPropagation();
    }

    bodyweightRequired(event) {
        showErrorToast(event.params.message);
    }

    async confirmNavigate(event) {
        event.preventDefault();

        const link = event.currentTarget;

        const confirmed = await confirmAction({
            icon:              'question',
            title:             link.dataset.title,
            text:              link.dataset.message,
            confirmButtonText: link.dataset.confirm,
            cancelButtonText:  link.dataset.cancel,
            reverseButtons:    true,
            customClass: {
                cancelButton: 'swal-cancel-btn',
                popup:        'swal-enurus-popup',
            },
        });

        if (!confirmed) {
            return;
        }

        window.location.href = link.href;
    }
}
