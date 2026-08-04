import { Controller } from '@hotwired/stimulus';
import { switchDashboardTab } from '../../utils/dashboard_tabs.js';

const DEFAULT_STATS = { sessions: 0, sessionsLabel: '', exercises: 0, exercisesLabel: '', sets: 0, setsLabel: '', reps: 0, prCount: 0, prLabel: '', repsRecordCount: 0, repsRecordLabel: '' };

export default class extends Controller {
    static values = {
        last: { type: Object, default: DEFAULT_STATS },
        week: { type: Object, default: DEFAULT_STATS },
        month: { type: Object, default: DEFAULT_STATS },
    };

    static targets = ['tab', 'sessions', 'sessionsLabel', 'exercises', 'exercisesLabel', 'sets', 'setsLabel', 'reps', 'prLabel', 'prIcon', 'repsRecordRow', 'repsRecordLabel'];

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

        this.sessionsTarget.textContent = stats.sessions;
        this.sessionsLabelTarget.textContent = stats.sessionsLabel;
        this.exercisesTarget.textContent = stats.exercises;
        this.exercisesLabelTarget.textContent = stats.exercisesLabel;
        this.setsTarget.textContent = stats.sets;
        this.setsLabelTarget.textContent = stats.setsLabel;
        this.repsTarget.textContent = stats.reps;
        this.prLabelTarget.textContent = stats.prLabel;
        this.prIconTarget.classList.toggle('hidden', stats.prCount === 0);

        this.repsRecordLabelTarget.textContent = stats.repsRecordLabel;
        this.repsRecordRowTarget.classList.toggle('hidden', stats.repsRecordCount === 0);
    }
}
