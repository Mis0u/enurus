import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const LanguageController = (await import('../../../../assets/controllers/settings/language_controller.js')).default;

function stubLocation() {
    Object.defineProperty(window, 'location', {
        configurable: true,
        value: { href: 'http://localhost/fr/reglages' },
    });
}

describe('settings--language controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers({ toFake: ['setTimeout'] });
        stubLocation();

        document.body.innerHTML = `
            <div data-controller="settings--language"
                 data-settings--language-url-value="/reglages/langue"
                 data-settings--language-csrf-token-value="token"
                 data-settings--language-success-message-value="Langue mise à jour">
                <select data-settings--language-target="select">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </select>
                <button data-action="click->settings--language#save"></button>
            </div>
        `;

        application = Application.start();
        application.register('settings--language', LanguageController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('posts the selected locale and redirects after showing a success toast', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ redirectUrl: '/en/settings' }),
        }));

        document.querySelector('[data-settings--language-target="select"]').value = 'en';
        document.querySelector('button').click();
        await vi.advanceTimersByTimeAsync(0);

        expect(global.fetch).toHaveBeenCalledWith('/reglages/langue', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ locale: 'en', _token: 'token' }),
        }));
        expect(showSuccessToast).toHaveBeenCalledWith('Langue mise à jour');

        await vi.advanceTimersByTimeAsync(500);
        expect(window.location.href).toBe('/en/settings');
    });

    it('does not redirect or toast when the server rejects the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        document.querySelector('button').click();
        await vi.advanceTimersByTimeAsync(0);
        await vi.advanceTimersByTimeAsync(500);

        expect(showSuccessToast).not.toHaveBeenCalled();
        expect(window.location.href).toBe('http://localhost/fr/reglages');
    });

    it('fails silently on a network error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        await expect(async () => {
            document.querySelector('button').click();
            await vi.advanceTimersByTimeAsync(0);
        }).not.toThrow();

        expect(showSuccessToast).not.toHaveBeenCalled();
    });
});
