import { Controller } from '@hotwired/stimulus';
import { switchDashboardTab } from '../../utils/dashboard_tabs.js';

const DEFAULT_STATS = { exercises: 0, sets: 0, reps: 0, prCount: 0, prLabel: '' };

export default class extends Controller {
    static values = {
        last: { type: Object, default: DEFAULT_STATS },
        week: { type: Object, default: DEFAULT_STATS },
        month: { type: Object, default: DEFAULT_STATS },
    };

    static targets = ['tab', 'exercises', 'sets', 'reps', 'prLabel', 'prIcon'];

    connect() {
        this.#applyStats('last');
    }

    switchFilter(event) {
        const filter = event.currentTarget.dataset.filter;

        switchDashboardTab(this.tabTargets, filter);

        this.#applyStats(filter);
    }

    #applyStats(filter) {
        const stats = this[`${filter}Value`];

        this.exercisesTarget.textContent = stats.exercises;
        this.setsTarget.textContent = stats.sets;
        this.repsTarget.textContent = stats.reps;
        this.prLabelTarget.textContent = stats.prLabel;
        this.prIconTarget.classList.toggle('hidden', stats.prCount === 0);
    }
}
