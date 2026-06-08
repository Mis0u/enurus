import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

export default class extends Controller {

    deleteWorkout(event) {
        event.stopPropagation();

        const btn      = event.currentTarget;

        Swal.fire({
            title:             btn.dataset.title,
            text:              btn.dataset.message,
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonText: btn.dataset.confirm,
            cancelButtonText:  btn.dataset.cancel,
            background:        '#0f1928',
            color:             '#f0f4ff',
            confirmButtonColor: '#f43f5e',
            cancelButtonColor:  'rgba(255,255,255,0.06)',
            reverseButtons:    true,
            customClass: {
                cancelButton: 'swal-cancel-btn',
                popup:        'swal-fittracker-popup',
            },
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(btn.dataset.deleteUrl, {
                method:  'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok) throw new Error('Erreur lors de la suppression');
                    window.location.reload();
                })
                .catch(() => {
                    Swal.fire({
                        icon:       'error',
                        title:      'Erreur',
                        text:       'La suppression a échoué, veuillez réessayer.',
                        background: '#0f1928',
                        color:      '#f0f4ff',
                        confirmButtonColor: '#f43f5e',
                    });
                });
        });
    }
}
