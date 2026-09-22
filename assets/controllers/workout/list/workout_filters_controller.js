import { Controller } from '@hotwired/stimulus';

const MUSCLE_STATES = ['none', 'primary', 'secondary'];

export default class extends Controller {
    static targets = ['dateInput', 'tabWeek', 'tabMonth', 'muscleChip'];

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

    /**
     * 1er clic = primaire, 2e clic = secondaire, 3e clic = retiré — même comportement que le
     * filtre muscle d'ExerciseSelectorComponent (recherche d'exercice). Combinable avec les
     * autres filtres (date/semaine/mois/routine), jamais exclusif : seul `page` est réinitialisé.
     */
    cycleMuscle(event) {
        const chip = event.currentTarget;
        const next = MUSCLE_STATES[(MUSCLE_STATES.indexOf(chip.dataset.muscleState) + 1) % MUSCLE_STATES.length];

        chip.dataset.muscleState = next;
        chip.classList.remove('pill--primary', 'pill--secondary');
        if (next !== 'none') {
            chip.classList.add(`pill--${next}`);
        }

        this.#syncMuscleFilterInUrl();
    }

    // ─── Privé ───────────────────────────────────────────────────

    #syncMuscleFilterInUrl() {
        const pairs = this.muscleChipTargets
            .filter(chip => chip.dataset.muscleState !== 'none')
            .map(chip => `${chip.dataset.muscleId}:${chip.dataset.muscleState}`);

        const url = new URL(window.location.href);

        if (pairs.length > 0) {
            url.searchParams.set('muscles', pairs.join(','));
        } else {
            url.searchParams.delete('muscles');
        }
        url.searchParams.delete('page');

        window.location.href = url.toString();
    }
}
