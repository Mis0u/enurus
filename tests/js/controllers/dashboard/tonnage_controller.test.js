import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import TonnageController from '../../../../assets/controllers/dashboard/tonnage_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function stubWidth(element, property, value) {
    Object.defineProperty(element, property, { configurable: true, value });
}

function chartView(values) {
    return JSON.stringify({ data: { datasets: [{ data: values }] } });
}

describe('dashboard--tonnage controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="dashboard--tonnage">
                <button data-dashboard--tonnage-target="tab" data-filter="sessions"
                        data-action="click->dashboard--tonnage#switchFilter" class="dashboard-tab-active"></button>
                <button data-dashboard--tonnage-target="tab" data-filter="week"
                        data-action="click->dashboard--tonnage#switchFilter" class="dashboard-tab-inactive"></button>
                <button data-dashboard--tonnage-target="tab" data-filter="month"
                        data-action="click->dashboard--tonnage#switchFilter" class="dashboard-tab-inactive"></button>

                <div data-dashboard--tonnage-target="chartPanel" data-filter="sessions">
                    <div class="inner"><canvas data-symfony--ux-chartjs--chart-view-value='${chartView([10, 20, 0, 0, 0])}'></canvas></div>
                </div>
                <div data-dashboard--tonnage-target="chartPanel" data-filter="week" class="hidden">
                    <div class="inner"><canvas data-symfony--ux-chartjs--chart-view-value='${chartView([5, 0, 15])}'></canvas></div>
                </div>
                <div data-dashboard--tonnage-target="chartPanel" data-filter="month" class="hidden">
                    <canvas data-symfony--ux-chartjs--chart-view-value='${chartView([1, 2, 3])}'></canvas>
                </div>
            </div>
        `;

        const sessionsPanel = document.querySelector('[data-dashboard--tonnage-target="chartPanel"][data-filter="sessions"]');
        stubWidth(sessionsPanel.querySelector('canvas').parentElement, 'offsetWidth', 500);
        stubWidth(sessionsPanel, 'clientWidth', 150);

        const weekPanel = document.querySelector('[data-dashboard--tonnage-target="chartPanel"][data-filter="week"]');
        stubWidth(weekPanel.querySelector('canvas').parentElement, 'offsetWidth', 300);
        stubWidth(weekPanel, 'clientWidth', 80);

        application = Application.start();
        application.register('dashboard--tonnage', TonnageController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('scrolls the sessions panel to the last active bar on connect', async () => {
        await nextTick();

        const panel = document.querySelector('[data-dashboard--tonnage-target="chartPanel"][data-filter="sessions"]');
        // barWidth = 500/5 = 100, last non-zero index = 1 -> targetRight = 200, scrollLeft = 200 - 150
        expect(panel.scrollLeft).toBe(50);
    });

    it('scrolls the week panel to the last active bar when switching filter', async () => {
        await nextTick();

        document.querySelector('[data-filter="week"]').click();

        const panel = document.querySelector('[data-dashboard--tonnage-target="chartPanel"][data-filter="week"]');
        // barWidth = 300/3 = 100, last non-zero index = 2 -> targetRight = 300, scrollLeft = 300 - 80
        expect(panel.scrollLeft).toBe(220);
    });

    it('does not throw and leaves scroll untouched when switching to month', async () => {
        await nextTick();

        expect(() => document.querySelector('[data-filter="month"]').click()).not.toThrow();

        const panel = document.querySelector('[data-dashboard--tonnage-target="chartPanel"][data-filter="month"]');
        expect(panel.scrollLeft).toBe(0);
    });
});
