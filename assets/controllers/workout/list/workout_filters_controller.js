import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dateInput', 'tabWeek', 'tabMonth'];

    onDateChange() {
        const date = this.dateInputTarget.value;

        if (!date) {
            return;
        }

        // Filtre exclusif : une date remplace la période (semaine/mois) et la routine.
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        url.searchParams.delete('filter');
        url.searchParams.delete('routine');
        url.searchParams.delete('page');

        window.location.href = url.toString();
    }

    clearDate() {
        const url = new URL(window.location.href);
        url.searchParams.delete('date');
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    // Combinable avec semaine/mois (filter), mais exclusif avec la date.
    onRoutineChange(event) {
        const routine = event.target.value;
        const url = new URL(window.location.href);

        if (routine) {
            url.searchParams.set('routine', routine);
        } else {
            url.searchParams.delete('routine');
        }

        url.searchParams.delete('date');
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }
}
