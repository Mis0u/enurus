import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

export default class extends Controller {
    static values = {
        checkUrl: String,
        message: String,
        confirmButton: String,
    };

    connect() {
        this.element.addEventListener('change', () => this.check());
    }

    async check() {
        const date = this.element.value;

        if (!date) return;

        const response = await fetch(`${this.checkUrlValue}?date=${date}`);

        if (!response.ok) return;

        const data = await response.json();

        if (!data.exists) return;

        Swal.fire({
            icon: 'info',
            text: data.message,
            confirmButtonText: this.confirmButtonValue,
            background: '#0f1928',
            color: '#f0f4ff',
            confirmButtonColor: '#f43f5e',
        });
    }
}
