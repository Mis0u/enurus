/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { estimateOneRepMax, MAX_REPS, RELIABLE_REPS, trainingLoads } from '../../utils/one_rep_max.js';

// Calculateur de 1RM public : tout se calcule dans le navigateur, à chaque frappe.
const PLATE_STEPS = { kg: 2.5, lbs: 5 };

const NO_LOAD = '—';

export default class extends Controller {
    static targets = ['weight', 'reps', 'unitButton', 'unitLabel', 'result', 'oneRepMax', 'invalid', 'warning', 'loads', 'loadRow'];

    static values = {
        unit: String,
    };

    #numberFormat = new Intl.NumberFormat(document.documentElement.lang, { maximumFractionDigits: 1 });

    #percentFormat = new Intl.NumberFormat(document.documentElement.lang, { style: 'percent' });

    connect() {
        this.repsTarget.max = MAX_REPS;
        this.compute();
    }

    unitValueChanged() {
        this.unitLabelTargets.forEach((label) => { label.textContent = this.unitValue; });
        this.unitButtonTargets.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.getAttribute(`data-${this.identifier}-unit-param`) === this.unitValue));
        });
        this.compute();
    }

    pickUnit({ params: { unit } }) {
        this.unitValue = unit;
    }

    compute() {
        const reps = Number(this.repsTarget.value);
        const oneRepMax = estimateOneRepMax(Number(this.weightTarget.value), reps);

        this.resultTarget.hidden = null === oneRepMax;
        this.invalidTarget.hidden = null !== oneRepMax;
        this.warningTarget.hidden = null === oneRepMax || reps <= RELIABLE_REPS;
        this.oneRepMaxTarget.textContent = null === oneRepMax ? '' : this.#numberFormat.format(oneRepMax);
        this.#fillLoads(oneRepMax);
    }

    #fillLoads(oneRepMax) {
        const rows = trainingLoads(oneRepMax ?? 0, PLATE_STEPS[this.unitValue]);

        this.loadsTarget.replaceChildren(...rows.map((row) => this.#loadRow(row, null !== oneRepMax)));
    }

    #loadRow({ percent, load, reps }, hasEstimate) {
        const row = this.loadRowTarget.content.firstElementChild.cloneNode(true);

        row.querySelector('[data-cell="percent"]').textContent = this.#percentFormat.format(percent / 100);
        row.querySelector('[data-cell="load"]').textContent = hasEstimate ? `${this.#numberFormat.format(load)} ${this.unitValue}` : NO_LOAD;
        row.querySelector('[data-cell="reps"]').textContent = 1 === reps ? '1' : `≈ ${reps}`;

        return row;
    }
}
