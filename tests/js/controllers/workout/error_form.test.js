import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    clearFieldError,
    handleErrorField,
    revealFirstInvalidField,
} from '../../../../assets/controllers/workout/_error_form.js';

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

    it('marks an invalid input as aria-invalid and unmarks it once filled', () => {
        document.body.innerHTML = `
            <div id="exercise-list">
                <input type="number" required data-error-message="Les répétitions sont requises">
            </div>
        `;
        const list = document.getElementById('exercise-list');
        const input = list.querySelector('input');

        handleErrorField(list);
        expect(input.getAttribute('aria-invalid')).toBe('true');

        input.value = '10';
        handleErrorField(list);
        expect(input.hasAttribute('aria-invalid')).toBe(false);
    });
});

describe('clearFieldError', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('removes the error border, the aria-invalid flag and the error message', () => {
        document.body.innerHTML = `
            <div id="exercise-list">
                <input type="number" required data-error-message="Les répétitions sont requises">
            </div>
        `;
        const list = document.getElementById('exercise-list');
        const input = list.querySelector('input');
        handleErrorField(list);

        clearFieldError(input);

        expect(input.hasAttribute('aria-invalid')).toBe(false);
        expect(input.className).toBe('');
        expect(document.querySelector('.js-error-message')).toBeNull();
    });
});

describe('revealFirstInvalidField', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('scrolls the first invalid input to the middle of the screen and focuses it', () => {
        document.body.innerHTML = `
            <div id="exercise-list">
                <input type="number" required value="80" data-error-message="Le poids est requis">
                <input type="number" required data-error-message="Les répétitions sont requises">
                <input type="number" required data-error-message="Les répétitions sont requises">
            </div>
        `;
        const list = document.getElementById('exercise-list');
        const [, firstInvalid, secondInvalid] = list.querySelectorAll('input');
        firstInvalid.scrollIntoView = vi.fn();
        secondInvalid.scrollIntoView = vi.fn();
        handleErrorField(list);

        revealFirstInvalidField(list);

        expect(firstInvalid.scrollIntoView).toHaveBeenCalledWith({ block: 'center', behavior: 'smooth' });
        expect(secondInvalid.scrollIntoView).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(firstInvalid);
    });

    it('does nothing when no field is invalid', () => {
        document.body.innerHTML = '<div id="exercise-list"><input type="number" required value="10"></div>';

        expect(() => revealFirstInvalidField(document.getElementById('exercise-list'))).not.toThrow();
    });
});
