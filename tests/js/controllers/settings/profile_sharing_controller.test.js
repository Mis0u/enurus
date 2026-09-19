import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const ProfileSharingController = (await import('../../../../assets/controllers/settings/profile_sharing_controller.js')).default;

describe('settings--profile-sharing controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="settings--profile-sharing"
                 data-settings--profile-sharing-url-value="/reglages/partage-de-profil"
                 data-settings--profile-sharing-csrf-token-value="token"
                 data-settings--profile-sharing-enable-message-value="Partage activé"
                 data-settings--profile-sharing-disable-message-value="Partage désactivé"
                 data-settings--profile-sharing-regenerate-message-value="Nouveau code généré"
                 data-settings--profile-sharing-copy-message-value="Code copié">
                <input type="checkbox" data-action="change->settings--profile-sharing#toggle">
                <div data-settings--profile-sharing-target="codeWrapper" hidden>
                    <span data-settings--profile-sharing-target="code">ABC123</span>
                    <button type="button" data-action="settings--profile-sharing#copy">Copier</button>
                    <button type="button" data-action="settings--profile-sharing#regenerate">Régénérer</button>
                </div>
            </div>
        `;

        application = Application.start();
        application.register('settings--profile-sharing', ProfileSharingController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('enabling sends the toggle action with enabled: true and reveals the code', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: true, shareCode: 'NEW123' }),
        }));

        const checkbox = document.querySelector('input');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/reglages/partage-de-profil', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ action: 'toggle', enabled: true, _token: 'token' }),
        }));
        expect(document.querySelector('[data-settings--profile-sharing-target="codeWrapper"]').hidden).toBe(false);
        expect(document.querySelector('[data-settings--profile-sharing-target="code"]').textContent).toBe('NEW123');
        expect(showSuccessToast).toHaveBeenCalledWith('Partage activé');
    });

    it('disabling sends the toggle action with enabled: false and hides the code', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: false, shareCode: 'ABC123' }),
        }));

        const checkbox = document.querySelector('input');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/reglages/partage-de-profil', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ action: 'toggle', enabled: false, _token: 'token' }),
        }));
        expect(document.querySelector('[data-settings--profile-sharing-target="codeWrapper"]').hidden).toBe(true);
        expect(showSuccessToast).toHaveBeenCalledWith('Partage désactivé');
    });

    it('regenerating sends the regenerate action and updates the displayed code', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: true, shareCode: 'ZZZ999' }),
        }));

        document.querySelector('button[data-action$="#regenerate"]').click();
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/reglages/partage-de-profil', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ action: 'regenerate', _token: 'token' }),
        }));
        expect(document.querySelector('[data-settings--profile-sharing-target="code"]').textContent).toBe('ZZZ999');
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
