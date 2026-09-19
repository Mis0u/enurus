import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast, showErrorToast } = await import('../../../../assets/utils/toast.js');
const ProfileSharingController = (await import('../../../../assets/controllers/profile_connection/profile_sharing_controller.js')).default;

describe('profile-connection--profile-sharing controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="profile-connection--profile-sharing"
                 data-profile-connection--profile-sharing-toggle-url-value="/connexions/partage"
                 data-profile-connection--profile-sharing-regenerate-url-value="/connexions/partage/regenerer"
                 data-profile-connection--profile-sharing-workout-toggle-url-value="/connexions/partage/seances"
                 data-profile-connection--profile-sharing-toggle-token-value="toggle-token"
                 data-profile-connection--profile-sharing-regenerate-token-value="regenerate-token"
                 data-profile-connection--profile-sharing-workout-toggle-token-value="workout-toggle-token"
                 data-profile-connection--profile-sharing-enable-message-value="Partage activé"
                 data-profile-connection--profile-sharing-disable-message-value="Partage désactivé"
                 data-profile-connection--profile-sharing-regenerate-message-value="Nouveau code généré"
                 data-profile-connection--profile-sharing-copy-message-value="Code copié"
                 data-profile-connection--profile-sharing-copy-error-message-value="Erreur de copie"
                 data-profile-connection--profile-sharing-enable-workouts-message-value="Partage des séances activé"
                 data-profile-connection--profile-sharing-disable-workouts-message-value="Partage des séances désactivé">
                <input type="checkbox" data-action="change->profile-connection--profile-sharing#toggle">
                <div data-profile-connection--profile-sharing-target="codeWrapper" hidden>
                    <span data-profile-connection--profile-sharing-target="fullCode">Misou#<span data-profile-connection--profile-sharing-target="code">ABC123</span></span>
                    <button type="button" data-action="profile-connection--profile-sharing#copy">Copier</button>
                    <button type="button" data-action="profile-connection--profile-sharing#regenerate">Régénérer</button>
                </div>
                <input type="checkbox"
                       data-profile-connection--profile-sharing-target="workoutCheckbox"
                       data-action="change->profile-connection--profile-sharing#toggleWorkouts">
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

    it('enabling sends isDiscoverable: true, reveals the code and dispatches a sharing-changed event', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: true, shareCode: 'NEW123', shareWorkouts: false }),
        }));
        const listener = vi.fn();
        window.addEventListener('profile-connection:sharing-changed', listener);

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
        expect(listener).toHaveBeenCalledWith(expect.objectContaining({ detail: { isDiscoverable: true } }));
        expect(document.querySelector('[data-profile-connection--profile-sharing-target="workoutCheckbox"]').disabled).toBe(false);

        window.removeEventListener('profile-connection:sharing-changed', listener);
    });

    it('disabling unchecks and disables the workout-sharing checkbox (server-side cascade reflected client-side)', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: false, shareCode: 'ABC123', shareWorkouts: false }),
        }));
        const workoutCheckbox = document.querySelector('[data-profile-connection--profile-sharing-target="workoutCheckbox"]');
        workoutCheckbox.checked = true;
        workoutCheckbox.disabled = false;

        const checkbox = document.querySelector('input[data-action$="#toggle"]');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(workoutCheckbox.checked).toBe(false);
        expect(workoutCheckbox.disabled).toBe(true);
    });

    it('disabling sends isDiscoverable: false, keeps the code visible and dispatches a sharing-changed event', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ isDiscoverable: false, shareCode: 'ABC123', shareWorkouts: false }),
        }));
        document.querySelector('[data-profile-connection--profile-sharing-target="codeWrapper"]').hidden = false;
        const listener = vi.fn();
        window.addEventListener('profile-connection:sharing-changed', listener);

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
        expect(listener).toHaveBeenCalledWith(expect.objectContaining({ detail: { isDiscoverable: false } }));

        window.removeEventListener('profile-connection:sharing-changed', listener);
    });

    it('toggling workout sharing on sends shareWorkouts: true and toasts success', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        const workoutCheckbox = document.querySelector('[data-profile-connection--profile-sharing-target="workoutCheckbox"]');
        workoutCheckbox.checked = true;
        workoutCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(global.fetch).toHaveBeenCalledWith('/connexions/partage/seances', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ shareWorkouts: true, _token: 'workout-toggle-token' }),
        }));
        expect(showSuccessToast).toHaveBeenCalledWith('Partage des séances activé');
    });

    it('reverts the checkbox when the server rejects the workout-sharing toggle', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const workoutCheckbox = document.querySelector('[data-profile-connection--profile-sharing-target="workoutCheckbox"]');
        workoutCheckbox.checked = true;
        workoutCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        expect(workoutCheckbox.checked).toBe(false);
        expect(showSuccessToast).not.toHaveBeenCalled();
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

    it('copy copies the full alias#code, preferring the synchronous execCommand fallback', async () => {
        document.execCommand = vi.fn().mockReturnValue(true);
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });

        document.querySelector('button[data-action$="#copy"]').click();
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(document.execCommand).toHaveBeenCalledWith('copy');
        expect(writeText).not.toHaveBeenCalled();
        expect(showSuccessToast).toHaveBeenCalledWith('Code copié');

        delete document.execCommand;
    });

    it('copy falls back to the Clipboard API with the full alias#code when execCommand is unavailable', async () => {
        document.execCommand = vi.fn().mockReturnValue(false);
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });

        document.querySelector('button[data-action$="#copy"]').click();
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalled());

        expect(writeText).toHaveBeenCalledWith('Misou#ABC123');
        expect(showSuccessToast).toHaveBeenCalledWith('Code copié');

        delete document.execCommand;
    });

    it('copy shows an error toast when both the fallback and the Clipboard API fail', async () => {
        document.execCommand = vi.fn().mockReturnValue(false);
        const writeText = vi.fn().mockRejectedValue(new Error('denied'));
        Object.assign(navigator, { clipboard: { writeText } });

        document.querySelector('button[data-action$="#copy"]').click();
        await vi.waitFor(() => expect(showErrorToast).toHaveBeenCalled());

        expect(showErrorToast).toHaveBeenCalledWith('Erreur de copie');
        expect(showSuccessToast).not.toHaveBeenCalled();

        delete document.execCommand;
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
