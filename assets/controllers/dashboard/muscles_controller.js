import { Controller } from '@hotwired/stimulus';
import { paintMuscleGroupsByIds, resetBodymap } from '../../utils/muscle_colors.js';
import { switchDashboardTab } from '../../utils/dashboard_tabs.js';

export default class extends Controller {
    static values = {
        sessionPrimary: { type: Array, default: [] },
        sessionSecondary: { type: Array, default: [] },
        weekPrimary: { type: Array, default: [] },
        weekSecondary: { type: Array, default: [] },
        monthPrimary: { type: Array, default: [] },
        monthSecondary: { type: Array, default: [] },
        weekMonthUnlocked: { type: Boolean, default: false },
    };

    static targets = ['tab', 'barsPanel'];

    /** @type {string[]} IDs des éléments SVG actuellement colorés */
    #activeIds = [];

    connect() {
        // État initial : toutes les zones bodymap en sombre (même comportement que workout--show--muscles)
        resetBodymap(this.element);

        this.#applyColors('session');
    }

    switchFilter(event) {
        const filter = event.currentTarget.dataset.filter;

        switchDashboardTab(this.tabTargets, filter);

        this.barsPanelTargets.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.filter !== filter);
        });

        this.#applyColors(filter);
    }

    #applyColors(filter) {
        // Reset : on remet à l'état neutre exactement les éléments qu'on avait colorés
        paintMuscleGroupsByIds(this.element, this.#activeIds, 'none');

        const primary = this[`${filter}PrimaryValue`];
        const secondary = this[`${filter}SecondaryValue`];

        this.#activeIds = [...primary, ...secondary];

        paintMuscleGroupsByIds(this.element, primary, 'primary');
        paintMuscleGroupsByIds(this.element, secondary, 'secondary');
    }
}
