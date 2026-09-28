import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const sortable = vi.hoisted(() => ({ options: null }));
vi.mock('sortablejs', () => ({
    default: class Sortable {
        constructor(el, options) {
            sortable.options = options;
        }

        destroy() {}
    },
}));

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const DashboardWidgetsController = (await import('../../../../assets/controllers/settings/dashboard_widgets_controller.js')).default;

describe('settings--dashboard-widgets controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="settings--dashboard-widgets"
                 data-settings--dashboard-widgets-url-value="/reglages/widgets"
                 data-settings--dashboard-widgets-csrf-token-value="token"
                 data-settings--dashboard-widgets-success-message-value="Préférences mises à jour">
                <input type="checkbox" checked
                       data-action="change->settings--dashboard-widgets#toggle"
                       data-settings--dashboard-widgets-widget-param="tonnage">
            </div>
        `;

        application = Application.start();
        application.register('settings--dashboard-widgets', DashboardWidgetsController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('unchecking a widget sends hidden: true for that widget', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const checkbox = document.querySelector('input');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/reglages/widgets', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ widget: 'tonnage', hidden: true, _token: 'token' }),
        }));
        expect(showSuccessToast).toHaveBeenCalledWith('Préférences mises à jour');
    });

    it('checking a widget sends hidden: false for that widget', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const checkbox = document.querySelector('input');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/reglages/widgets', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ widget: 'tonnage', hidden: false, _token: 'token' }),
        }));
    });

    it('does not toast when the server rejects the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const checkbox = document.querySelector('input');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('fails silently on a network error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        await expect(async () => {
            const checkbox = document.querySelector('input');
            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());
        }).not.toThrow();

        expect(showSuccessToast).not.toHaveBeenCalled();
    });
});

describe('settings--dashboard-widgets controller — widget order', () => {
    let application;

    const rows = () => [...document.querySelectorAll('[data-settings--dashboard-widgets-target="row"]')];
    const order = () => rows().map((row) => row.dataset.widget);
    const row = (widget) => document.querySelector(`[data-widget="${widget}"]`);
    const button = (widget, direction) => row(widget).querySelector(`[data-settings--dashboard-widgets-target="${direction}Button"]`);
    const sentOrder = () => JSON.parse(global.fetch.mock.calls.at(-1)[1].body).order;

    function widgetRow(widget) {
        return `
            <li data-settings--dashboard-widgets-target="row" data-widget="${widget}">
                <button data-settings--dashboard-widgets-target="moveUpButton" data-action="settings--dashboard-widgets#moveUp"></button>
                <button data-settings--dashboard-widgets-target="moveDownButton" data-action="settings--dashboard-widgets#moveDown"></button>
            </li>`;
    }

    beforeEach(async () => {
        vi.clearAllMocks();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        document.body.innerHTML = `
            <div data-controller="settings--dashboard-widgets"
                 data-settings--dashboard-widgets-url-value="/reglages/widgets"
                 data-settings--dashboard-widgets-order-url-value="/reglages/widgets/ordre"
                 data-settings--dashboard-widgets-csrf-token-value="token"
                 data-settings--dashboard-widgets-success-message-value="Préférences mises à jour">
                <ul data-settings--dashboard-widgets-target="list">
                    ${['session', 'tonnage', 'badges'].map(widgetRow).join('')}
                </ul>
            </div>
        `;

        application = Application.start();
        application.register('settings--dashboard-widgets', DashboardWidgetsController);
        await new Promise((resolve) => setTimeout(resolve, 0));
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('moves a widget up and saves the new order', async () => {
        button('badges', 'moveUp').click();
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(order()).toEqual(['session', 'badges', 'tonnage']);
        expect(global.fetch).toHaveBeenCalledWith('/reglages/widgets/ordre', expect.objectContaining({ method: 'PATCH' }));
        expect(sentOrder()).toEqual(['session', 'badges', 'tonnage']);
        expect(showSuccessToast).toHaveBeenCalledWith('Préférences mises à jour');
    });

    it('moves a widget down and saves the new order', async () => {
        button('session', 'moveDown').click();
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(sentOrder()).toEqual(['tonnage', 'session', 'badges']);
    });

    it('keeps the focus on the arrow that was used, so it can be pressed again', () => {
        button('badges', 'moveUp').focus();
        button('badges', 'moveUp').click();

        expect(document.activeElement).toBe(button('badges', 'moveUp'));
    });

    it('disables the up arrow of the first widget and the down arrow of the last one', async () => {
        expect(button('session', 'moveUp').disabled).toBe(true);
        expect(button('badges', 'moveDown').disabled).toBe(true);
        expect(button('tonnage', 'moveUp').disabled).toBe(false);

        button('badges', 'moveUp').click();
        button('badges', 'moveUp').click();

        expect(button('badges', 'moveUp').disabled).toBe(true);
        expect(button('tonnage', 'moveDown').disabled).toBe(true);
    });

    it('saves the order after a drag and drop', async () => {
        row('session').before(row('badges'));
        sortable.options.onEnd();
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(sentOrder()).toEqual(['badges', 'session', 'tonnage']);
        expect(button('badges', 'moveUp').disabled).toBe(true);
    });

    it('drags widgets by their handle only', () => {
        expect(sortable.options.handle).toBe('.drag-handle');
    });
});

