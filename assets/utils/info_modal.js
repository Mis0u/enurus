import Swal from 'sweetalert2';

export function showInfoModal({ title, body, confirmLabel }) {
    if (typeof Swal === 'undefined') {
        return;
    }

    Swal.fire({
        icon: 'info',
        title,
        html: body,
        confirmButtonText: confirmLabel,
        confirmButtonColor: '#f43f5e',
        background: '#0f1928',
        color: '#f0f4ff',
    });
}
