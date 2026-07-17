import { Controller } from '@hotwired/stimulus';
import { confirmAction } from '../utils/delete_confirmation.js';

export default class extends Controller {
    stop(event) {
        event.stopPropagation();
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
                popup:        'swal-fittracker-popup',
            },
        });

        if (!confirmed) {
            return;
        }

        window.location.href = link.href;
    }
}
