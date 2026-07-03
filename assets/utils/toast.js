import Swal from 'sweetalert2';

export function showSuccessToast(message) {
    if (typeof Swal === 'undefined') {
        return;
    }

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#0f1928',
        color: '#f0f4ff',
        iconColor: '#22c55e',
    });
}
