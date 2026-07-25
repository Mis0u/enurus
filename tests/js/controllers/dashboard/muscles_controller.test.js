import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import MusclesController from '../../../../assets/controllers/dashboard/muscles_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="dashboard--muscles"
             data-dashboard--muscles-session-primary-value='["chest"]'
             data-dashboard--muscles-session-secondary-value='["triceps"]'
             data-dashboard--muscles-week-primary-value='["quads"]'
             data-dashboard--muscles-week-secondary-value='["calves"]'
             data-dashboard--muscles-month-primary-value='[]'
             data-dashboard--muscles-month-secondary-value='[]'>
            <g class="bodymap" id="chest"></g>
            <g class="bodymap" id="triceps"></g>
            <g class="bodymap" id="quads"></g>
            <g class="bodymap" id="calves"></g>

            <button data-dashboard--muscles-target="tab" data-filter="session"
                    data-action="click->dashboard--muscles#switchFilter" class="dashboard-tab-active"></button>
            <button data-dashboard--muscles-target="tab" data-filter="week"
                    data-action="click->dashboard--muscles#switchFilter" class="dashboard-tab-inactive"></button>

            <div data-dashboard--muscles-target="barsPanel" data-filter="session"></div>
            <div data-dashboard--muscles-target="barsPanel" data-filter="week" class="hidden"></div>
        </div>
    `;
}

describe('dashboard--muscles controller', () => {
    let application;

    beforeEach(() => {
        buildDom();
        application = Application.start();
        application.register('dashboard--muscles', MusclesController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('colors the session muscles on connect and leaves the rest idle', async () => {
        await nextTick();

        expect(document.getElementById('chest').style.stroke).toBe('#f43f5e');
        expect(document.getElementById('triceps').style.stroke).toBe('#06b6d4');
        expect(document.getElementById('quads').style.fill).toBe('#1e293b');
        expect(document.getElementById('calves').style.fill).toBe('#1e293b');
    });

    it('switches colors and active tab when filtering by week', async () => {
        await nextTick();

        document.querySelector('[data-filter="week"][data-dashboard--muscles-target="tab"]').click();

        expect(document.getElementById('quads').style.stroke).toBe('#f43f5e');
        expect(document.getElementById('calves').style.stroke).toBe('#06b6d4');
        // Les muscles de "session" ne sont plus actifs : reset à la couleur idle.
        expect(document.getElementById('chest').style.fill).toBe('#1e293b');
        expect(document.getElementById('triceps').style.fill).toBe('#1e293b');

        const sessionTab = document.querySelector('[data-filter="session"][data-dashboard--muscles-target="tab"]');
        const weekTab = document.querySelector('[data-filter="week"][data-dashboard--muscles-target="tab"]');
        expect(sessionTab.classList.contains('dashboard-tab-inactive')).toBe(true);
        expect(weekTab.classList.contains('dashboard-tab-active')).toBe(true);

        const sessionPanel = document.querySelector('[data-filter="session"][data-dashboard--muscles-target="barsPanel"]');
        const weekPanel = document.querySelector('[data-filter="week"][data-dashboard--muscles-target="barsPanel"]');
        expect(sessionPanel.classList.contains('hidden')).toBe(true);
        expect(weekPanel.classList.contains('hidden')).toBe(false);
    });
});
