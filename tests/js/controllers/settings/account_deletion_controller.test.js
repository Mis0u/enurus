import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn();
const showValidationMessageMock = vi.fn();

vi.mock('sweetalert2', () => ({
    default: {
        fire: fireMock,
        showValidationMessage: showValidationMessageMock,
    },
}));

const AccountDeletionController = (await import('../../../../assets/controllers/settings/account_deletion_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function stubLocation() {
    Object.defineProperty(window, 'location', {
        configurable: true,
        value: { href: 'http://localhost/fr/reglages' },
    });
}

describe('settings--account-deletion controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();
        stubLocation();

        document.body.innerHTML = `
            <div data-controller="settings--account-deletion"
                 data-settings--account-deletion-url-value="/reglages/suppression-compte"
                 data-settings--account-deletion-csrf-token-value="token"
                 data-settings--account-deletion-nickname-value="Misou"
                 data-settings--account-deletion-title-value="Titre"
                 data-settings--account-deletion-body-value="Corps"
                 data-settings--account-deletion-placeholder-value="Pseudo"
                 data-settings--account-deletion-mismatch-error-value="Pseudo incorrect"
                 data-settings--account-deletion-confirm-label-value="Confirmer"
                 data-settings--account-deletion-cancel-label-value="Annuler"
                 data-settings--account-deletion-request-error-value="Erreur serveur">
                <button data-action="click->settings--account-deletion#openModal"></button>
            </div>
        `;

        application = Application.start();
        application.register('settings--account-deletion', AccountDeletionController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('opens a SweetAlert2 modal with the translated title and confirm label', () => {
        fireMock.mockReturnValue({ then: () => {} });

        document.querySelector('button').click();

        expect(fireMock).toHaveBeenCalledWith(expect.objectContaining({
            title: 'Titre',
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler',
        }));
    });

    it('preConfirm rejects a nickname that does not match and shows the mismatch error', () => {
        fireMock.mockReturnValue({ then: () => {} });

        document.querySelector('button').click();
        const { preConfirm } = fireMock.mock.calls[0][0];

        expect(preConfirm('wrong-nickname')).toBe(false);
        expect(showValidationMessageMock).toHaveBeenCalledWith('Pseudo incorrect');
    });

    it('preConfirm accepts the exact nickname', () => {
        fireMock.mockReturnValue({ then: () => {} });

        document.querySelector('button').click();
        const { preConfirm } = fireMock.mock.calls[0][0];

        expect(preConfirm('Misou')).toBe(true);
        expect(showValidationMessageMock).not.toHaveBeenCalled();
    });

    it('requests deletion and redirects to the logout URL when confirmed', async () => {
        fireMock.mockReturnValue(Promise.resolve({ isConfirmed: true }));
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ logoutUrl: '/deconnexion' }),
        }));

        document.querySelector('button').click();
        await nextTick();
        await nextTick();

        expect(global.fetch).toHaveBeenCalledWith('/reglages/suppression-compte', expect.objectContaining({ method: 'POST' }));
        expect(window.location.href).toBe('/deconnexion');
    });

    it('does not call the API when the modal is dismissed', async () => {
        fireMock.mockReturnValue(Promise.resolve({ isConfirmed: false }));
        vi.stubGlobal('fetch', vi.fn());

        document.querySelector('button').click();
        await nextTick();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('shows an error alert when the deletion request fails', async () => {
        fireMock.mockReturnValue(Promise.resolve({ isConfirmed: true }));
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        document.querySelector('button').click();
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenLastCalledWith(expect.objectContaining({
            icon: 'error',
            text: 'Erreur serveur',
        }));
    });

    it('shows an error alert on a network failure', async () => {
        fireMock.mockReturnValue(Promise.resolve({ isConfirmed: true }));
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        document.querySelector('button').click();
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenLastCalledWith(expect.objectContaining({ icon: 'error' }));
    });
});
