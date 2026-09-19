import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const SharedWidgetsController = (await import('../../../../assets/controllers/profile_connection/shared_widgets_controller.js')).default;

describe('profile-connection--shared-widgets controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="profile-connection--shared-widgets"
                 data-profile-connection--shared-widgets-url-value="/connexions/partage/widgets"
                 data-profile-connection--shared-widgets-csrf-token-value="token"
                 data-profile-connection--shared-widgets-success-message-value="Préférence mise à jour">
                <input type="checkbox" checked
                       data-profile-connection--shared-widgets-target="checkbox"
                       data-action="change->profile-connection--shared-widgets#toggle"
                       data-profile-connection--shared-widgets-widget-param="tonnage">
                <input type="checkbox" checked
                       data-profile-connection--shared-widgets-target="checkbox"
                       data-action="change->profile-connection--shared-widgets#toggle"
                       data-profile-connection--shared-widgets-widget-param="session">
            </div>
        `;

        application = Application.start();
        application.register('profile-connection--shared-widgets', SharedWidgetsController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('unchecking sends hiddenForShare: true for that widget', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const checkbox = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/connexions/partage/widgets', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ widget: 'tonnage', hiddenForShare: true, _token: 'token' }),
        }));
        expect(showSuccessToast).toHaveBeenCalledWith('Préférence mise à jour');
    });

    it('checking sends hiddenForShare: false for that widget', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const checkbox = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/connexions/partage/widgets', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ widget: 'tonnage', hiddenForShare: false, _token: 'token' }),
        }));
    });

    it('does not toast when the server rejects the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const checkbox = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('reverts the checkbox when the server rejects the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const checkbox = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(checkbox.checked).toBe(true);
    });

    it('fails silently on a network error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        await expect(async () => {
            const checkbox = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());
        }).not.toThrow();

        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('unchecks and disables every checkbox when profile sharing is disabled', async () => {
        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: false, shareWorkouts: false, hiddenSharedWidgets: ['tonnage', 'session'] },
        }));

        document.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            expect(checkbox.checked).toBe(false);
            expect(checkbox.disabled).toBe(true);
        });
    });

    it('unchecks and disables every checkbox when workout sharing is disabled', async () => {
        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: true, shareWorkouts: false, hiddenSharedWidgets: ['tonnage', 'session'] },
        }));

        document.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            expect(checkbox.checked).toBe(false);
            expect(checkbox.disabled).toBe(true);
        });
    });

    it('re-enables and restores checked state once both parents are active again', async () => {
        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: true, shareWorkouts: true, hiddenSharedWidgets: ['tonnage'] },
        }));

        const tonnage = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
        const session = document.querySelector('input[data-profile-connection--shared-widgets-widget-param="session"]');

        expect(tonnage.disabled).toBe(false);
        expect(tonnage.checked).toBe(false);
        expect(session.disabled).toBe(false);
        expect(session.checked).toBe(true);
    });
});
