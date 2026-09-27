import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const EmailNotificationController = (await import('../../../../assets/controllers/profile_connection/email_notification_controller.js')).default;

describe('profile-connection--email-notification controller', () => {
    let application;

    const checkbox = () => document.querySelector('input[type="checkbox"]');

    const toggleTo = async (checked) => {
        checkbox().checked = checked;
        checkbox().dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());
    };

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <section data-controller="profile-connection--email-notification"
                     data-profile-connection--email-notification-url-value="/connexions/notifications"
                     data-profile-connection--email-notification-csrf-token-value="token"
                     data-profile-connection--email-notification-success-message-value="Préférence mise à jour">
                <input type="checkbox" checked data-action="change->profile-connection--email-notification#toggle">
            </section>
        `;

        application = Application.start();
        application.register('profile-connection--email-notification', EmailNotificationController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('sends the new state and confirms it', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        await toggleTo(false);

        expect(global.fetch).toHaveBeenCalledWith('/connexions/notifications', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ enabled: false, _token: 'token' }),
        }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalledWith('Préférence mise à jour'));
    });

    it('restores the switch when the server rejects the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        await toggleTo(false);

        await vi.waitFor(() => expect(checkbox().checked).toBe(true));
        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('restores the switch when the network fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await toggleTo(false);

        await vi.waitFor(() => expect(checkbox().checked).toBe(true));
    });
});
