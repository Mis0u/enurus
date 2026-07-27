import { afterEach, describe, expect, it, vi } from 'vitest';
import { initNameChecker } from '../../../../assets/controllers/routine/routine-name-checker.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildController({ excludeId } = {}) {
    document.body.innerHTML = `
        <div><input id="name-input" value=""></div>
    `;

    return {
        nameInputTarget: document.getElementById('name-input'),
        checkNameUrlValue: '/routine/verifie-nom',
        hasExcludeIdValue: undefined !== excludeId,
        excludeIdValue: excludeId,
        errorNameExistsValue: 'Ce nom existe déjà',
        setNameAvailable: vi.fn(),
    };
}

describe('routine name checker', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('does nothing when the field is blurred empty', async () => {
        const controller = buildController();
        vi.stubGlobal('fetch', vi.fn());
        initNameChecker(controller);

        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        expect(global.fetch).not.toHaveBeenCalled();
        expect(controller.setNameAvailable).not.toHaveBeenCalled();
    });

    it('queries the check-name endpoint with the trimmed name on blur', async () => {
        const controller = buildController();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ available: true }),
        }));
        initNameChecker(controller);

        controller.nameInputTarget.value = '  Push day  ';
        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        const calledUrl = new URL(global.fetch.mock.calls[0][0]);
        expect(calledUrl.pathname).toBe('/routine/verifie-nom');
        expect(calledUrl.searchParams.get('name')).toBe('Push day');
        expect(controller.setNameAvailable).toHaveBeenCalledWith(true);
    });

    it('includes excludeId in the query when provided (edit mode)', async () => {
        const controller = buildController({ excludeId: 'routine-42' });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ available: true }),
        }));
        initNameChecker(controller);

        controller.nameInputTarget.value = 'Push day';
        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        const calledUrl = new URL(global.fetch.mock.calls[0][0]);
        expect(calledUrl.searchParams.get('excludeId')).toBe('routine-42');
    });

    it('shows a field error and reports unavailable when the name already exists', async () => {
        const controller = buildController();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ available: false }),
        }));
        initNameChecker(controller);

        controller.nameInputTarget.value = 'Push day';
        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        expect(controller.nameInputTarget.classList.contains('border-rose-500')).toBe(true);
        expect(controller.nameInputTarget.parentElement.querySelector('.field-error').textContent).toBe('Ce nom existe déjà');
        expect(controller.setNameAvailable).toHaveBeenCalledWith(false);
    });

    it('clears the error and reports available again once the user edits the field', async () => {
        const controller = buildController();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ available: false }),
        }));
        initNameChecker(controller);

        controller.nameInputTarget.value = 'Push day';
        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        controller.nameInputTarget.dispatchEvent(new Event('input'));

        expect(controller.nameInputTarget.classList.contains('border-rose-500')).toBe(false);
        expect(controller.nameInputTarget.parentElement.querySelector('.field-error')).toBeNull();
        expect(controller.setNameAvailable).toHaveBeenLastCalledWith(true);
    });

    it('treats a network error as available, deferring validation to the backend', async () => {
        const controller = buildController();
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));
        initNameChecker(controller);

        controller.nameInputTarget.value = 'Push day';
        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        expect(controller.setNameAvailable).not.toHaveBeenCalled();
        expect(controller.nameInputTarget.classList.contains('border-rose-500')).toBe(false);
    });

    it('treats a non-ok response as available without erroring', async () => {
        const controller = buildController();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
        initNameChecker(controller);

        controller.nameInputTarget.value = 'Push day';
        controller.nameInputTarget.dispatchEvent(new Event('blur'));
        await nextTick();

        expect(controller.setNameAvailable).toHaveBeenCalledWith(true);
    });
});
