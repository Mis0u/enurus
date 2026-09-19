import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

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
                 data-settings--dashboard-widgets-share-url-value="/reglages/widgets/partage"
                 data-settings--dashboard-widgets-csrf-token-value="token"
                 data-settings--dashboard-widgets-share-csrf-token-value="share-token"
                 data-settings--dashboard-widgets-success-message-value="Préférences mises à jour">
                <div data-widget-key="tonnage">
                    <input type="checkbox" checked
                           data-action="change->settings--dashboard-widgets#toggle"
                           data-settings--dashboard-widgets-widget-param="tonnage">
                    <input type="checkbox"
                           data-action="change->settings--dashboard-widgets#toggleShare"
                           data-settings--dashboard-widgets-widget-param="tonnage">
                </div>
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

    it('hiding a widget disables the "hide for connections" checkbox for the same widget', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const checkbox = document.querySelector('input[data-action$="#toggle"]');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        const shareCheckbox = document.querySelector('input[data-action$="#toggleShare"]');
        expect(shareCheckbox.disabled).toBe(true);
    });

    it('showing a widget again re-enables the "hide for connections" checkbox', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
        document.querySelector('input[data-action$="#toggleShare"]').disabled = true;

        const checkbox = document.querySelector('input[data-action$="#toggle"]');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        const shareCheckbox = document.querySelector('input[data-action$="#toggleShare"]');
        expect(shareCheckbox.disabled).toBe(false);
    });

    it('checking "hide for connections" sends hiddenForShare: true for that widget', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const shareCheckbox = document.querySelector('input[data-action$="#toggleShare"]');
        shareCheckbox.checked = true;
        shareCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/reglages/widgets/partage', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ widget: 'tonnage', hiddenForShare: true, _token: 'share-token' }),
        }));
        expect(showSuccessToast).toHaveBeenCalledWith('Préférences mises à jour');
    });

    it('does not toast when the server rejects the share update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const shareCheckbox = document.querySelector('input[data-action$="#toggleShare"]');
        shareCheckbox.checked = true;
        shareCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
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
