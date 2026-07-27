import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import WorkoutFiltersController from '../../../../assets/controllers/workout/list/workout_filters_controller.js';

function stubLocation(href) {
    Object.defineProperty(window, 'location', {
        configurable: true,
        value: { href },
    });
}

describe('workout--list--workout-filters controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="workout--list--workout-filters">
                <input type="date" data-workout--list--workout-filters-target="dateInput"
                       data-action="change->workout--list--workout-filters#onDateChange">
                <button data-action="click->workout--list--workout-filters#clearDate"></button>
            </div>
        `;

        application = Application.start();
        application.register('workout--list--workout-filters', WorkoutFiltersController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('navigates with the date query param and drops filter and page', () => {
        stubLocation('http://localhost/fr/mes-seances?filter=week&page=2');

        const dateInput = document.querySelector('[data-workout--list--workout-filters-target="dateInput"]');
        dateInput.value = '2026-01-14';
        dateInput.dispatchEvent(new Event('change'));

        const url = new URL(window.location.href);
        expect(url.searchParams.get('date')).toBe('2026-01-14');
        expect(url.searchParams.has('filter')).toBe(false);
        expect(url.searchParams.has('page')).toBe(false);
    });

    it('does nothing when the date input is cleared', () => {
        stubLocation('http://localhost/fr/mes-seances?filter=week');

        const dateInput = document.querySelector('[data-workout--list--workout-filters-target="dateInput"]');
        dateInput.value = '';
        dateInput.dispatchEvent(new Event('change'));

        expect(window.location.href).toBe('http://localhost/fr/mes-seances?filter=week');
    });

    it('clearDate navigates dropping only date and page, keeping other filters', () => {
        stubLocation('http://localhost/fr/mes-seances?date=2026-01-14&page=2&filter=week');

        document.querySelector('button').click();

        const url = new URL(window.location.href);
        expect(url.searchParams.has('date')).toBe(false);
        expect(url.searchParams.has('page')).toBe(false);
        expect(url.searchParams.get('filter')).toBe('week');
    });
});
