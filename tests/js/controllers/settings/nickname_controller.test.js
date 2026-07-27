import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const NicknameController = (await import('../../../../assets/controllers/settings/nickname_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('settings--nickname controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="settings--nickname"
                 data-settings--nickname-url-value="/reglages/pseudo"
                 data-settings--nickname-csrf-token-value="token"
                 data-settings--nickname-min-length-value="3"
                 data-settings--nickname-max-length-value="20"
                 data-settings--nickname-min-error-value="Trop court"
                 data-settings--nickname-max-error-value="Trop long"
                 data-settings--nickname-success-message-value="Enregistré">
                <input data-settings--nickname-target="input" value="">
                <div data-settings--nickname-target="error" class="hidden"></div>
                <button data-action="click->settings--nickname#save"></button>
            </div>
        `;

        application = Application.start();
        application.register('settings--nickname', NicknameController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    function input() {
        return document.querySelector('[data-settings--nickname-target="input"]');
    }

    function error() {
        return document.querySelector('[data-settings--nickname-target="error"]');
    }

    it('shows the min-length error and does not call the API', async () => {
        vi.stubGlobal('fetch', vi.fn());
        input().value = 'ab';

        document.querySelector('button').click();
        await nextTick();

        expect(error().textContent).toBe('Trop court');
        expect(error().classList.contains('hidden')).toBe(false);
        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('shows the max-length error for a value over the limit', async () => {
        vi.stubGlobal('fetch', vi.fn());
        input().value = 'a'.repeat(21);

        document.querySelector('button').click();
        await nextTick();

        expect(error().textContent).toBe('Trop long');
    });

    it('persists a valid nickname and shows a success toast', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
        input().value = 'Misou';

        document.querySelector('button').click();
        await nextTick();

        expect(global.fetch).toHaveBeenCalledWith('/reglages/pseudo', expect.objectContaining({
            method: 'PATCH',
            body: JSON.stringify({ nickname: 'Misou', _token: 'token' }),
        }));
        expect(showSuccessToast).toHaveBeenCalledWith('Enregistré');
    });

    it('broadcasts the new initials computed from the nickname', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
        const listener = vi.fn();
        window.addEventListener('user:nickname-updated', listener);
        input().value = 'misou';

        document.querySelector('button').click();
        await nextTick();

        expect(listener.mock.calls[0][0].detail).toEqual({ initials: 'MU' });
        window.removeEventListener('user:nickname-updated', listener);
    });

    it('shows an error when the server rejects the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
        input().value = 'Misou';

        document.querySelector('button').click();
        await nextTick();

        expect(error().textContent).toBe('Trop long');
        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('shows an error on a network failure', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));
        input().value = 'Misou';

        document.querySelector('button').click();
        await nextTick();

        expect(error().classList.contains('hidden')).toBe(false);
    });
});
