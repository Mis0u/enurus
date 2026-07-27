import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import ChartPercentTooltipController from '../../../../assets/controllers/admin/chart_percent_tooltip_controller.js';

describe('admin--chart-percent-tooltip controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = '<canvas data-controller="admin--chart-percent-tooltip"></canvas>';
        application = Application.start();
        application.register('admin--chart-percent-tooltip', ChartPercentTooltipController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    function element() {
        return document.querySelector('[data-controller="admin--chart-percent-tooltip"]');
    }

    it('adds a "%" suffix to the tooltip label using the raw value', () => {
        const options = {};
        element().dispatchEvent(new CustomEvent('chartjs:pre-connect', { detail: { options } }));

        const label = options.plugins.tooltip.callbacks.label({ label: 'Français', raw: 42 });

        expect(label).toBe('Français: 42%');
    });

    it('falls back to the dataset label when the context has no direct label', () => {
        const options = {};
        element().dispatchEvent(new CustomEvent('chartjs:pre-connect', { detail: { options } }));

        const label = options.plugins.tooltip.callbacks.label({ dataset: { label: 'Utilisateurs' }, raw: 10 });

        expect(label).toBe('Utilisateurs: 10%');
    });

    it('preserves any existing plugins configuration already set by the chart', () => {
        const options = { plugins: { legend: { display: false } } };
        element().dispatchEvent(new CustomEvent('chartjs:pre-connect', { detail: { options } }));

        expect(options.plugins.legend).toEqual({ display: false });
        expect(options.plugins.tooltip.callbacks.label).toBeInstanceOf(Function);
    });
});
