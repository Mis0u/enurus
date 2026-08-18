import { afterEach, describe, expect, it } from 'vitest';
import { handleErrorField } from '../../../../assets/controllers/workout/_error_form.js';

describe('handleErrorField', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('ignores disabled required inputs, e.g. a bodyweight exercise card blocked by missing bodyweight', () => {
        document.body.innerHTML = `
            <div id="exercise-list">
                <input type="number" required disabled data-error-message="Les répétitions sont requises">
            </div>
        `;

        const valid = handleErrorField(document.getElementById('exercise-list'));

        expect(valid).toBe(true);
        expect(document.querySelector('.js-error-message')).toBeNull();
    });

    it('still flags an empty required input when it is not disabled', () => {
        document.body.innerHTML = `
            <div id="exercise-list">
                <input type="number" required data-error-message="Les répétitions sont requises">
            </div>
        `;

        const valid = handleErrorField(document.getElementById('exercise-list'));

        expect(valid).toBe(false);
        expect(document.querySelector('.js-error-message')?.textContent).toBe('Les répétitions sont requises');
    });
});
