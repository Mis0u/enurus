import Swal from 'sweetalert2';
export function swalError(title, text, background, color, buttonColor) {
    Swal.fire({
        icon: 'error',
        title: title,
        text: text,
        background: background,
        color: color,
        confirmButtonColor: buttonColor,
    });
}
