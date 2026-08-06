import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast, showErrorToast } = await import('../../../../assets/utils/toast.js');
const PasswordSubmitController = (await import('../../../../assets/controllers/settings/password_submit_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('settings--password-submit controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <form data-controller="settings--password-submit"
                  data-settings--password-submit-url-value="/reglages/mot-de-passe"
                  data-settings--password-submit-success-message-value="Changement effectué"
                  data-settings--password-submit-error-message-value="Une erreur est survenue, réessaie plus tard"
                  data-action="submit->settings--password-submit#submit">
                <input type="password" name="change_password_form[currentPassword]">
                <input type="password" name="change_password_form[plainPassword][first]">
                <input type="password" name="change_password_form[plainPassword][second]">
                <p data-field-error="currentPassword" class="hidden"></p>
                <button type="submit" data-settings--password-submit-target="submitButton"></button>
            </form>
        `;

        application = Application.start();
        application.register('settings--password-submit', PasswordSubmitController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    function form() {
        return document.querySelector('form');
    }

    function currentPasswordInput() {
        return document.querySelector('[name="change_password_form[currentPassword]"]');
    }

    function submitButton() {
        return form().querySelector('button[type="submit"]');
    }

    function submit() {
        return submitButton().click();
    }

    it('shows a success toast and resets the form on success', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true }),
        }));

        currentPasswordInput().value = 'old';
        submit();
        await nextTick();

        expect(showSuccessToast).toHaveBeenCalledWith('Changement effectué');
        expect(currentPasswordInput().value).toBe('');
    });

    it('re-syncs the password-validator disabled state after a successful reset', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true }),
        }));

        const inputListener = vi.fn();
        currentPasswordInput().addEventListener('input', inputListener);

        submit();
        await nextTick();

        // form.reset() ne déclenche pas d'event input nativement — le contrôleur doit le
        // redéclencher manuellement pour que password-validator revalide les champs vidés.
        expect(inputListener).toHaveBeenCalled();
    });

    it('displays inline field errors and does not toast on validation failure', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ errors: { currentPassword: ['Le mot de passe actuel n\'est pas correct'] } }),
        }));

        submit();
        await nextTick();

        const error = form().querySelector('[data-field-error="currentPassword"]');
        expect(error.textContent).toBe('Le mot de passe actuel n\'est pas correct');
        expect(showSuccessToast).not.toHaveBeenCalled();
        expect(showErrorToast).not.toHaveBeenCalled();
    });

    it('shows an error toast when the request fails unexpectedly', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        submit();
        await nextTick();

        expect(showErrorToast).toHaveBeenCalledWith('Une erreur est survenue, réessaie plus tard');
        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('shows an error toast when the response body is not valid JSON', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => {
                throw new SyntaxError('Unexpected token');
            },
        }));

        submit();
        await nextTick();

        expect(showErrorToast).toHaveBeenCalledWith('Une erreur est survenue, réessaie plus tard');
        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('disables the button and shows a spinner while the request is in flight', async () => {
        let resolveFetch;
        vi.stubGlobal('fetch', vi.fn(() => new Promise((resolve) => {
            resolveFetch = resolve;
        })));

        submit();
        await nextTick();

        expect(submitButton().disabled).toBe(true);
        expect(submitButton().querySelector('[data-submit-spinner]')).not.toBeNull();

        resolveFetch({ ok: true, json: async () => ({ success: true }) });
        await nextTick();
    });

    it('removes the spinner once the request resolves', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true }),
        }));

        submit();
        await nextTick();

        expect(submitButton().querySelector('[data-submit-spinner]')).toBeNull();
    });

    it('removes the spinner and re-enables the button on validation error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ errors: { currentPassword: ['Le mot de passe actuel n\'est pas correct'] } }),
        }));

        submit();
        await nextTick();

        expect(submitButton().querySelector('[data-submit-spinner]')).toBeNull();
        expect(submitButton().disabled).toBe(false);
    });

    it('removes the spinner and re-enables the button on network error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        submit();
        await nextTick();

        expect(submitButton().querySelector('[data-submit-spinner]')).toBeNull();
        expect(submitButton().disabled).toBe(false);
    });
});
