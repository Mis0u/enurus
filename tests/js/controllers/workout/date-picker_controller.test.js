import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import { French } from '../../stubs/flatpickr-l10n.js';

const destroyMock = vi.fn();
const flatpickrMock = vi.fn(() => ({ destroy: destroyMock }));

vi.mock('flatpickr', () => ({ default: flatpickrMock }));

const DatePickerController = (await import('../../../../assets/controllers/workout/date-picker_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom(locale = '') {
    document.body.innerHTML = `
        <input
            type="text"
            readonly
            data-controller="workout--date-picker"
            data-workout--date-picker-locale-value="${locale}"
        >
    `;
}

describe('workout--date-picker controller', () => {
    let application;

    beforeEach(() => {
        flatpickrMock.mockClear();
        destroyMock.mockClear();
        application = Application.start();
        application.register('workout--date-picker', DatePickerController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('initializes flatpickr on the input with today as the max date', async () => {
        buildDom();
        await nextTick();

        const input = document.querySelector('input');

        expect(flatpickrMock).toHaveBeenCalledTimes(1);
        expect(flatpickrMock.mock.calls[0][0]).toBe(input);
        expect(flatpickrMock.mock.calls[0][1]).toMatchObject({
            dateFormat: 'Y-m-d',
            maxDate: 'today',
        });
    });

    it('defaults to the English locale when no locale value is set', async () => {
        buildDom();
        await nextTick();

        expect(flatpickrMock.mock.calls[0][1].locale).toBe('default');
    });

    it('applies the matching flatpickr locale when one is provided', async () => {
        buildDom('fr');
        await nextTick();

        expect(flatpickrMock.mock.calls[0][1].locale).toBe(French);
    });

    it('destroys the flatpickr instance on disconnect', async () => {
        buildDom();
        await nextTick();

        document.querySelector('input').remove();
        await nextTick();

        expect(destroyMock).toHaveBeenCalledTimes(1);
    });
});
