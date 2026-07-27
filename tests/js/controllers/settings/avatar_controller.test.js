import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import AvatarController from '../../../../assets/controllers/settings/avatar_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="settings--avatar"
             data-settings--avatar-upload-url-value="/reglages/avatar"
             data-settings--avatar-delete-url-value="/reglages/avatar/supprime"
             data-settings--avatar-delete-csrf-token-value="token"
             data-settings--avatar-max-size-value="2097152"
             data-settings--avatar-allowed-types-value='["image/jpeg","image/png"]'
             data-settings--avatar-too-large-error-value="Fichier trop volumineux"
             data-settings--avatar-invalid-type-error-value="Type invalide">
            <input type="file" data-settings--avatar-target="input"
                   data-action="change->settings--avatar#upload">
            <img data-settings--avatar-target="image" class="hidden">
            <span data-settings--avatar-target="initials">MU</span>
            <div data-settings--avatar-target="spinner" class="hidden"></div>
            <button data-settings--avatar-target="removeButton" class="hidden!"
                    data-action="click->settings--avatar#remove"></button>
            <div data-settings--avatar-target="error" class="hidden">
                <span data-settings--avatar-target="errorText"></span>
            </div>
        </div>
    `;
}

function fileInput() {
    return document.querySelector('[data-settings--avatar-target="input"]');
}

function setInputFile(file) {
    Object.defineProperty(fileInput(), 'files', { configurable: true, value: [file] });
    fileInput().dispatchEvent(new Event('change'));
}

function jpeg(name, size = 1024) {
    return new File([new Uint8Array(size)], name, { type: 'image/jpeg' });
}

describe('settings--avatar controller', () => {
    let application;

    beforeEach(() => {
        buildDom();
        application = Application.start();
        application.register('settings--avatar', AvatarController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('rejects a disallowed file type and clears the input', async () => {
        vi.stubGlobal('fetch', vi.fn());
        setInputFile(new File(['x'], 'doc.pdf', { type: 'application/pdf' }));
        await nextTick();

        expect(document.querySelector('[data-settings--avatar-target="errorText"]').textContent).toBe('Type invalide');
        expect(fileInput().value).toBe('');
        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('rejects a file larger than the configured max size', async () => {
        vi.stubGlobal('fetch', vi.fn());
        setInputFile(jpeg('big.jpg', 3_000_000));
        await nextTick();

        expect(document.querySelector('[data-settings--avatar-target="errorText"]').textContent).toBe('Fichier trop volumineux');
    });

    it('uploads a valid file, applies the returned image and broadcasts the update', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ url: '/media/avatars/me.jpg' }),
        }));

        const listener = vi.fn();
        window.addEventListener('user:avatar-updated', listener);

        setInputFile(jpeg('avatar.jpg'));
        await nextTick();
        await nextTick();

        const image = document.querySelector('[data-settings--avatar-target="image"]');
        expect(image.src).toContain('/media/avatars/me.jpg');
        expect(image.classList.contains('hidden')).toBe(false);
        expect(document.querySelector('[data-settings--avatar-target="initials"]').classList.contains('hidden')).toBe(true);
        expect(listener.mock.calls[0][0].detail).toEqual({ url: '/media/avatars/me.jpg' });

        window.removeEventListener('user:avatar-updated', listener);
    });

    it('shows an error when the upload fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        setInputFile(jpeg('avatar.jpg'));
        await nextTick();
        await nextTick();

        expect(document.querySelector('[data-settings--avatar-target="errorText"]').textContent).toBe('Fichier trop volumineux');
    });

    it('updates the initials from a nickname-updated event broadcast elsewhere', () => {
        window.dispatchEvent(new CustomEvent('user:nickname-updated', { detail: { initials: 'AB' } }));

        expect(document.querySelector('[data-settings--avatar-target="initials"]').textContent).toBe('AB');
    });

    it('remove() falls back to initials and broadcasts a null avatar url on success', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true }),
        }));

        const listener = vi.fn();
        window.addEventListener('user:avatar-updated', listener);

        document.querySelector('[data-settings--avatar-target="removeButton"]').click();
        await nextTick();
        await nextTick();

        expect(document.querySelector('[data-settings--avatar-target="image"]').classList.contains('hidden')).toBe(true);
        expect(document.querySelector('[data-settings--avatar-target="initials"]').classList.contains('hidden')).toBe(false);
        expect(listener.mock.calls[0][0].detail).toEqual({ url: null });

        window.removeEventListener('user:avatar-updated', listener);
    });

    it('remove() does nothing visible when the delete request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        document.querySelector('[data-settings--avatar-target="removeButton"]').click();
        await nextTick();
        await nextTick();

        expect(document.querySelector('[data-settings--avatar-target="initials"]').classList.contains('hidden')).toBe(false);
    });
});
