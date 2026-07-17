import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';
import { confirmDeletion, sendDeleteRequest, showDeleteError } from '../../../utils/delete_confirmation.js';

export default class extends Controller {

    async deleteWorkout(event) {
        event.stopPropagation();

        const btn = event.currentTarget;

        const confirmed = await confirmDeletion({
            title:             btn.dataset.title,
            text:              btn.dataset.message,
            confirmButtonText: btn.dataset.confirm,
            cancelButtonText:  btn.dataset.cancel,
            reverseButtons:    true,
            customClass: {
                cancelButton: 'swal-cancel-btn',
                popup:        'swal-fittracker-popup',
            },
        });

        if (!confirmed) {
            return;
        }

        const { ok, data } = await sendDeleteRequest(btn.dataset.deleteUrl, btn.dataset.token);

        if (!ok || !data?.success) {
            showDeleteError({ title: btn.dataset.errorText });
            return;
        }

        Swal.fire({
            toast:             true,
            position:           'top-end',
            icon:               'success',
            title:              data.message,
            showConfirmButton:  false,
            timer:              1200,
            timerProgressBar:   true,
            background:         '#0f1928',
            color:              '#f0f4ff',
            iconColor:          '#22c55e',
        });
        setTimeout(() => window.location.reload(), 1200);
    }
}
