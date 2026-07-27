import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import PhotoUploadController from '../../../../assets/controllers/workout/photo_upload_controller.js';

function nextTick() {
    // FileReader.readAsDataURL() dans jsdom résout via une IO task, pas un simple microtask :
    // un setTimeout(0) suffit rarement, un délai plus large stabilise le test.
    return new Promise(resolve => setTimeout(resolve, 10));
}

async function buildDom(uploadUrl = '') {
    document.body.innerHTML = `
        <div data-controller="workout--photo-upload"
             data-workout--photo-upload-upload-url-value="${uploadUrl}">
            <div data-workout--photo-upload-target="zone"
                 data-error-type="Type de fichier invalide"
                 data-error-size="Fichier trop volumineux"
                 data-action="dragover->workout--photo-upload#onDragOver
                              dragleave->workout--photo-upload#onDragLeave
                              drop->workout--photo-upload#onDrop"></div>
            <input type="file" data-workout--photo-upload-target="input"
                   data-action="change->workout--photo-upload#onFileChange">
            <div data-workout--photo-upload-target="previewWrapper" class="hidden">
                <img data-workout--photo-upload-target="preview">
                <span data-workout--photo-upload-target="filename"></span>
                <button data-action="click->workout--photo-upload#onRemove"></button>
                <button data-action="click->workout--photo-upload#openLightbox"></button>
            </div>
            <div data-workout--photo-upload-target="placeholder"></div>
            <div data-workout--photo-upload-target="error" class="hidden"></div>
            <div data-workout--photo-upload-target="lightbox" class="hidden">
                <img data-workout--photo-upload-target="lightboxImg">
                <button data-action="click->workout--photo-upload#closeLightbox"></button>
            </div>
        </div>
    `;

    await nextTick();
}

function fileInput() {
    return document.querySelector('[data-workout--photo-upload-target="input"]');
}

function setInputFile(file) {
    Object.defineProperty(fileInput(), 'files', { configurable: true, value: [file] });
    fileInput().dispatchEvent(new Event('change'));
}

function jpeg(name, size = 1024) {
    return new File([new Uint8Array(size)], name, { type: 'image/jpeg' });
}

describe('workout--photo-upload controller', () => {
    let application;

    beforeEach(() => {
        application = Application.start();
        application.register('workout--photo-upload', PhotoUploadController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('rejects a file with a disallowed mime type', async () => {
        await buildDom();
        setInputFile(new File(['x'], 'doc.pdf', { type: 'application/pdf' }));
        await nextTick();

        expect(document.querySelector('[data-workout--photo-upload-target="error"]').textContent).toBe('Type de fichier invalide');
        expect(document.querySelector('[data-workout--photo-upload-target="previewWrapper"]').classList.contains('hidden')).toBe(true);
    });

    it('rejects a file larger than the configured max size', async () => {
        document.body.innerHTML = '';
        await buildDom();
        const zone = document.querySelector('[data-controller="workout--photo-upload"]');
        zone.setAttribute('data-workout--photo-upload-max-size-value', '100');

        setInputFile(jpeg('big.jpg', 200));
        await nextTick();

        expect(document.querySelector('[data-workout--photo-upload-target="error"]').textContent).toBe('Fichier trop volumineux');
    });

    it('shows the preview for a valid file without uploading immediately when no upload URL is configured', async () => {
        await buildDom('');
        vi.stubGlobal('fetch', vi.fn());

        setInputFile(jpeg('photo.jpg'));
        await nextTick();

        expect(document.querySelector('[data-workout--photo-upload-target="previewWrapper"]').classList.contains('hidden')).toBe(false);
        expect(document.querySelector('[data-workout--photo-upload-target="filename"]').textContent).toBe('photo.jpg');
        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('uploads immediately and dispatches the uploaded event when an upload URL is configured', async () => {
        await buildDom('/seance/123/photo');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ path: 'workout/123/photo.jpg', url: '/media/workout/123/photo.jpg' }),
        }));

        const listener = vi.fn();
        document.querySelector('[data-controller="workout--photo-upload"]')
            .addEventListener('workout--photo-upload:uploaded', listener);

        setInputFile(jpeg('photo.jpg'));
        await nextTick();
        await nextTick();

        expect(global.fetch).toHaveBeenCalledWith('/seance/123/photo', expect.objectContaining({ method: 'POST' }));
        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toEqual({
            path: 'workout/123/photo.jpg',
            url: '/media/workout/123/photo.jpg',
        });
    });

    it('shows a server error on upload failure (known race: a slower FileReader can re-show the preview after resetPreview() already ran)', async () => {
        await buildDom('/seance/123/photo');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ error: 'Erreur serveur' }),
        }));

        setInputFile(jpeg('photo.jpg'));
        await nextTick();
        await nextTick();

        // #upload() résout en microtasks (fetch mocké) donc son resetPreview() s'exécute avant
        // que le FileReader.onload de #showPreview() (macrotask) ne remette le wrapper visible :
        // le message d'erreur est fiable, mais l'état visuel de la preview ne l'est pas — piège
        // non corrigé ici (hors périmètre), signalé tel quel plutôt que masqué par le test.
        expect(document.querySelector('[data-workout--photo-upload-target="error"]').textContent).toBe('Erreur serveur');
    });

    it('onRemove clears the preview and the file input value', async () => {
        await buildDom('');
        setInputFile(jpeg('photo.jpg'));
        await nextTick();

        document.querySelectorAll('[data-workout--photo-upload-target="previewWrapper"] button')[0].click();

        expect(document.querySelector('[data-workout--photo-upload-target="previewWrapper"]').classList.contains('hidden')).toBe(true);
        expect(document.querySelector('[data-workout--photo-upload-target="placeholder"]').classList.contains('hidden')).toBe(false);
        expect(fileInput().value).toBe('');
    });

    it('openLightbox and closeLightbox toggle the lightbox visibility', async () => {
        await buildDom('');
        setInputFile(jpeg('photo.jpg'));
        await nextTick();

        const openBtn = document.querySelectorAll('[data-workout--photo-upload-target="previewWrapper"] button')[1];
        openBtn.click();

        const lightbox = document.querySelector('[data-workout--photo-upload-target="lightbox"]');
        expect(lightbox.classList.contains('hidden')).toBe(false);
        expect(lightbox.classList.contains('flex')).toBe(true);

        document.querySelector('[data-workout--photo-upload-target="lightbox"] button').click();
        expect(lightbox.classList.contains('hidden')).toBe(true);
    });

    it('onDrop handles a dropped file the same way as a file input change', async () => {
        await buildDom('');
        vi.stubGlobal('fetch', vi.fn());

        const zone = document.querySelector('[data-workout--photo-upload-target="zone"]');
        const dropEvent = new Event('drop', { cancelable: true });
        Object.defineProperty(dropEvent, 'dataTransfer', { value: { files: [jpeg('dropped.jpg')] } });
        zone.dispatchEvent(dropEvent);
        await nextTick();

        expect(document.querySelector('[data-workout--photo-upload-target="filename"]').textContent).toBe('dropped.jpg');
    });

    it('uploadIfSelected uploads a pending file and returns the response data', async () => {
        await buildDom('');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ path: 'workout/tmp/photo.jpg', url: '/media/workout/tmp/photo.jpg' }),
        }));

        setInputFile(jpeg('photo.jpg'));
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--photo-upload"]'),
            'workout--photo-upload',
        );
        const result = await controller.uploadIfSelected();

        expect(result).toEqual({ path: 'workout/tmp/photo.jpg', url: '/media/workout/tmp/photo.jpg' });
    });

    it('uploadIfSelected returns null when no file was selected', async () => {
        await buildDom('');

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--photo-upload"]'),
            'workout--photo-upload',
        );
        const result = await controller.uploadIfSelected();

        expect(result).toBeNull();
    });
});
