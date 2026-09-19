import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const ProfileSharingController = (await import('../../../../assets/controllers/profile_connection/profile_sharing_controller.js')).default;

describe('profile-connection--profile-sharing controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="profile-connection--profile-sharing"
                 data-profile-connection--profile-sharing-toggle-url-value="/connexions/partage"
                 data-profile-connection--profile-sharing-regenerate-url-value="/connexions/partage/regenerer"
                 data-profile-connection--profile-sharing-toggle-token-value="toggle-token"
                 data-profile-connection--profile-sharing-regenerate-token-value="regenerate-token"
                 data-profile-connection--profile-sharing-enable-message-value="Partage activé"
                 data-profile-connection--profile-sharing-disable-message-value="Partage désactivé"
                 data-profile-connection--profile-sharing-regenerate-message-value="Nouveau code généré"
                 data-profile-connection--profile-sharing-copy-message-value="Code copié">
                <input type="checkbox" data-action="change->profile-connection--profile-sharing#toggle">
                <div data-profile-connection--profile-sharing-target="codeWrapper" hidden>
                    <span data-profile-connection--profile-sharing-target="code">ABC123</span>
                    <button type="button" data-action="profile-connection--profile-sharing#copy">Copier</button>
                    <button type="button" data-action="profile-connection--profile-sharing#regenerate">Régénérer</button>
                </div>
            </div>
        `;

        application = Application.start();
        application.register('profile-connection--profile-sharing', ProfileSharingController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('enabling sends isDiscoverable: true and reveals the code', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: true, shareCode: 'NEW123' }),
        }));

        const checkbox = document.querySelector('input');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/connexions/partage', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ isDiscoverable: true, _token: 'toggle-token' }),
        }));
        expect(document.querySelector('[data-profile-connection--profile-sharing-target="codeWrapper"]').hidden).toBe(false);
        expect(document.querySelector('[data-profile-connection--profile-sharing-target="code"]').textContent).toBe('NEW123');
        expect(showSuccessToast).toHaveBeenCalledWith('Partage activé');
    });

    it('disabling sends isDiscoverable: false and keeps the code visible', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: false, shareCode: 'ABC123' }),
        }));
        document.querySelector('[data-profile-connection--profile-sharing-target="codeWrapper"]').hidden = false;

        const checkbox = document.querySelector('input');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/connexions/partage', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ isDiscoverable: false, _token: 'toggle-token' }),
        }));
        expect(document.querySelector('[data-profile-connection--profile-sharing-target="codeWrapper"]').hidden).toBe(false);
        expect(showSuccessToast).toHaveBeenCalledWith('Partage désactivé');
    });

    it('regenerating sends the regenerate token and updates the displayed code', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: true, shareCode: 'ZZZ999' }),
        }));

        document.querySelector('button[data-action$="#regenerate"]').click();
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/connexions/partage/regenerer', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ _token: 'regenerate-token' }),
        }));
        expect(document.querySelector('[data-profile-connection--profile-sharing-target="code"]').textContent).toBe('ZZZ999');
        expect(showSuccessToast).toHaveBeenCalledWith('Nouveau code généré');
    });

    it('copy writes the current code to the clipboard', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });

        document.querySelector('button[data-action$="#copy"]').click();
        await vi.waitFor(() => expect(writeText).toHaveBeenCalled());

        expect(writeText).toHaveBeenCalledWith('ABC123');
        expect(showSuccessToast).toHaveBeenCalledWith('Code copié');
    });

    it('does not toast when the server rejects the toggle', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const checkbox = document.querySelector('input');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('fails silently on a network error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        await expect(async () => {
            const checkbox = document.querySelector('input');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());
        }).not.toThrow();

        expect(showSuccessToast).not.toHaveBeenCalled();
    });
});
