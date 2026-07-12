import { Controller } from '@hotwired/stimulus';
import { paintMuscleGroupsByIds, resetBodymap } from '../../../utils/muscle_colors.js';

export default class extends Controller {
    static values = {
        primary: Array,
        secondary: Array,
    };

    connect() {
        this.applyColors();
    }

    applyColors() {
        const container = this.element;

        resetBodymap(container);
        paintMuscleGroupsByIds(container, this.primaryValue, 'primary');
        paintMuscleGroupsByIds(container, this.secondaryValue, 'secondary');
    }

    disconnect() {
        if (this._injectedStyle) {
            this._injectedStyle.remove();
        }
    }
}
