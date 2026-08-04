import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import LocalDatetimeController from '../../../assets/controllers/local-datetime_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom({ iso = '2026-08-04T16:32:00Z', locale = 'fr', style = 'datetime' } = {}) {
    document.body.innerHTML = `
        <span
            data-controller="local-datetime"
            data-local-datetime-iso-value="${iso}"
            data-local-datetime-locale-value="${locale}"
            data-local-datetime-style-value="${style}"
        >SSR-FALLBACK</span>
    `;
}

describe('local-datetime controller', () => {
    let application;

    beforeEach(() => {
        application = Application.start();
        application.register('local-datetime', LocalDatetimeController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('replaces the server-rendered fallback with a locale-formatted datetime', async () => {
        buildDom();
        await nextTick();

        const text = document.querySelector('span').textContent;
        expect(text).not.toBe('SSR-FALLBACK');
        expect(text).toContain('2026');
    });

    it('never shows AM/PM regardless of locale — always 24h', async () => {
        buildDom({ locale: 'en' });
        await nextTick();

        const text = document.querySelector('span').textContent;
        expect(text).not.toMatch(/AM|PM/);
    });

    it('renders a date-only value without a time component for the "date" style', async () => {
        buildDom({ style: 'date' });
        await nextTick();

        const text = document.querySelector('span').textContent;
        expect(text).not.toContain(':');
    });

    it('renders a numeric day/month/year for the "datetime-numeric" style', async () => {
        buildDom({ style: 'datetime-numeric', locale: 'fr' });
        await nextTick();

        const text = document.querySelector('span').textContent;
        expect(text).toMatch(/\d{2}\/\d{2}\/2026/);
    });

    it('keeps the server-rendered fallback when the iso value is invalid', async () => {
        buildDom({ iso: 'not-a-date' });
        await nextTick();

        expect(document.querySelector('span').textContent).toBe('SSR-FALLBACK');
    });
});
