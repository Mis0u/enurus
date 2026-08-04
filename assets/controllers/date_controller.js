import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

export default class extends Controller {
    static values = {
        checkUrl: String,
        message: String,
        confirmButton: String,
        excludeId: String,
    };

    connect() {
        this.element.addEventListener('change', () => this.check());
    }

    async check() {
        const date = this.element.value;

        if (!date) return;

        const url = new URL(this.checkUrlValue, window.location.origin);
        url.searchParams.set('date', date);

        if (this.hasExcludeIdValue && this.excludeIdValue) {
            url.searchParams.set('excludeId', this.excludeIdValue);
        }

        const response = await fetch(url.toString());

        if (!response.ok) return;

        const data = await response.json();

        if (!data.exists) return;

        // returnFocus: false — SweetAlert2 remet sinon le focus sur le champ ayant déclenché
        // l'ouverture (notre input) une fois la modale fermée, et flatpickr rouvre aussitôt le
        // calendrier puisqu'il s'ouvre au focus, pas seulement au clic (cf. bug). Cette restauration
        // de focus a lieu après la résolution de la promesse Swal.fire(), donc un blur() placé
        // après ne suffit pas à l'empêcher — il faut la désactiver à la source.
        await Swal.fire({
            icon: 'info',
            text: data.message,
            confirmButtonText: this.confirmButtonValue,
            background: '#0f1928',
            color: '#f0f4ff',
            confirmButtonColor: '#f43f5e',
            returnFocus: false,
        });
    }
}
