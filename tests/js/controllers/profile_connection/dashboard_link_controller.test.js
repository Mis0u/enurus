import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/info_modal.js', () => ({
    showInfoModal: vi.fn(),
}));

const { showInfoModal } = await import('../../../../assets/utils/info_modal.js');
const DashboardLinkController = (await import('../../../../assets/controllers/profile_connection/dashboard_link_controller.js')).default;

describe('profile-connection--dashboard-link controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <a href="/connexions/1/tableau-de-bord"
               data-controller="profile-connection--dashboard-link"
               data-profile-connection--dashboard-link-blocked-value="true"
               data-profile-connection--dashboard-link-title-value="Partage désactivé"
               data-profile-connection--dashboard-link-body-value="Réactive le partage"
               data-profile-connection--dashboard-link-confirm-label-value="Compris"
               data-action="click->profile-connection--dashboard-link#guard">
                Voir le dashboard
            </a>
        `;

        application = Application.start();
        application.register('profile-connection--dashboard-link', DashboardLinkController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('prevents navigation and shows an info modal when blocked', () => {
        const link = document.querySelector('a');
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(showInfoModal).toHaveBeenCalledWith({
            title: 'Partage désactivé',
            body: 'Réactive le partage',
            confirmLabel: 'Compris',
        });
    });

    it('lets navigation proceed when not blocked', () => {
        document.querySelector('a').setAttribute('data-profile-connection--dashboard-link-blocked-value', 'false');

        const link = document.querySelector('a');
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(showInfoModal).not.toHaveBeenCalled();
    });

    it('becomes blocked when the viewer turns their own sharing off', () => {
        document.querySelector('a').setAttribute('data-profile-connection--dashboard-link-blocked-value', 'false');

        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: false },
        }));

        const link = document.querySelector('a');
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(showInfoModal).toHaveBeenCalled();
    });

    it('becomes unblocked when the viewer turns their own sharing back on', () => {
        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: true },
        }));

        const link = document.querySelector('a');
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(showInfoModal).not.toHaveBeenCalled();
    });
});
