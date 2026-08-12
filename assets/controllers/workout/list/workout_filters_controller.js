import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dateInput', 'tabWeek', 'tabMonth'];

    onDateChange() {
        const date = this.dateInputTarget.value;

        if (!date) {
            return;
        }

        // Construit l'URL avec le query param date, sans filter
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        url.searchParams.delete('filter');
        url.searchParams.delete('page');

        window.location.href = url.toString();
    }

    clearDate() {
        const url = new URL(window.location.href);
        url.searchParams.delete('date');
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    // Filtre indépendant de la période (date/semaine/mois) — combinable, jamais retiré ici.
    onRoutineChange(event) {
        const routine = event.target.value;
        const url = new URL(window.location.href);

        if (routine) {
            url.searchParams.set('routine', routine);
        } else {
            url.searchParams.delete('routine');
        }

        url.searchParams.delete('page');
        window.location.href = url.toString();
    }
}
