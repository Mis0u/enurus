import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const { toBlobMock } = vi.hoisted(() => ({ toBlobMock: vi.fn() }));

vi.mock('html-to-image', () => ({ toBlob: toBlobMock }), { virtual: true });
vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showSuccessToast, showErrorToast } = await import('../../../../assets/utils/toast.js');
const ShareController = (await import('../../../../assets/controllers/workout/share_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('workout--share controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();
        toBlobMock.mockReset();

        document.body.innerHTML = `
            <div data-controller="workout--share"
                 data-workout--share-download-success-value="Téléchargé"
                 data-workout--share-error-value="Erreur"
                 data-workout--share-caption-share-value="Partager cette séance"
                 data-workout--share-caption-download-only-value="Télécharge puis partage manuellement">
                <div data-workout--share-target="card"></div>
                <button data-workout--share-target="button" data-action="click->workout--share#share"></button>
                <div data-workout--share-target="modal" class="hidden">
                    <img data-workout--share-target="previewImage">
                    <button data-workout--share-target="shareButton"></button>
                    <span data-workout--share-target="caption"></span>
                </div>
            </div>
        `;

        global.URL.createObjectURL = vi.fn(() => 'blob:preview-url');
        global.URL.revokeObjectURL = vi.fn();

        application = Application.start();
        application.register('workout--share', ShareController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    function controllerButton() {
        return document.querySelector('[data-workout--share-target="button"]');
    }

    function modal() {
        return document.querySelector('[data-workout--share-target="modal"]');
    }

    it('opens the preview modal with the captured image on success', async () => {
        const blob = new Blob(['fake'], { type: 'image/png' });
        toBlobMock.mockResolvedValue(blob);
        vi.stubGlobal('navigator', { ...navigator, canShare: () => true });

        controllerButton().click();
        await nextTick();
        await nextTick();

        expect(modal().classList.contains('hidden')).toBe(false);
        expect(modal().classList.contains('flex')).toBe(true);
        expect(document.querySelector('[data-workout--share-target="previewImage"]').src).toBe('blob:preview-url');
        expect(controllerButton().disabled).toBe(false);
    });

    it('shows the share button only when the browser can share files, with a download-only hint otherwise', async () => {
        const blob = new Blob(['fake'], { type: 'image/png' });
        toBlobMock.mockResolvedValue(blob);
        vi.stubGlobal('navigator', { ...navigator, canShare: () => false });

        controllerButton().click();
        await nextTick();
        await nextTick();

        expect(document.querySelector('[data-workout--share-target="shareButton"]').hidden).toBe(true);
        expect(document.querySelector('[data-workout--share-target="caption"]').textContent).toBe('Télécharge puis partage manuellement');
    });

    it('shows an error toast and re-enables the button when capture fails', async () => {
        toBlobMock.mockRejectedValue(new Error('capture failed'));

        controllerButton().click();
        await nextTick();
        await nextTick();

        expect(showErrorToast).toHaveBeenCalledWith('Erreur');
        expect(controllerButton().disabled).toBe(false);
        expect(modal().classList.contains('hidden')).toBe(true);
    });

    it('shows an error toast when capture resolves with no data', async () => {
        toBlobMock.mockResolvedValue(null);

        controllerButton().click();
        await nextTick();
        await nextTick();

        expect(showErrorToast).toHaveBeenCalledWith('Erreur');
    });

    it('closePreview hides the modal and revokes the object URL', async () => {
        toBlobMock.mockResolvedValue(new Blob(['fake'], { type: 'image/png' }));
        controllerButton().click();
        await nextTick();
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--share"]'),
            'workout--share',
        );
        controller.closePreview();

        expect(modal().classList.contains('hidden')).toBe(true);
        expect(global.URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-url');
    });

    it('downloadFromPreview does nothing once the preview has been closed', async () => {
        toBlobMock.mockResolvedValue(new Blob(['fake'], { type: 'image/png' }));
        controllerButton().click();
        await nextTick();
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--share"]'),
            'workout--share',
        );
        controller.closePreview();
        controller.downloadFromPreview();

        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('downloadFromPreview triggers a download and shows a success toast', async () => {
        toBlobMock.mockResolvedValue(new Blob(['fake'], { type: 'image/png' }));
        controllerButton().click();
        await nextTick();
        await nextTick();

        const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--share"]'),
            'workout--share',
        );
        controller.downloadFromPreview();

        expect(clickSpy).toHaveBeenCalledTimes(1);
        expect(showSuccessToast).toHaveBeenCalledWith('Téléchargé');
        expect(modal().classList.contains('hidden')).toBe(true);

        clickSpy.mockRestore();
    });

    it('shareFromPreview calls navigator.share with the captured image and closes the preview', async () => {
        toBlobMock.mockResolvedValue(new Blob(['fake'], { type: 'image/png' }));
        const shareMock = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { ...navigator, canShare: () => true, share: shareMock });

        controllerButton().click();
        await nextTick();
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--share"]'),
            'workout--share',
        );
        await controller.shareFromPreview();

        expect(shareMock).toHaveBeenCalledTimes(1);
        expect(modal().classList.contains('hidden')).toBe(true);
    });

    it('shareFromPreview silently ignores an AbortError (user cancelled the native share sheet)', async () => {
        toBlobMock.mockResolvedValue(new Blob(['fake'], { type: 'image/png' }));
        const abortError = new Error('cancelled');
        abortError.name = 'AbortError';
        vi.stubGlobal('navigator', { ...navigator, canShare: () => true, share: vi.fn().mockRejectedValue(abortError) });

        controllerButton().click();
        await nextTick();
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--share"]'),
            'workout--share',
        );
        await controller.shareFromPreview();

        expect(showErrorToast).not.toHaveBeenCalled();
    });

    it('shareFromPreview shows an error toast for a non-abort failure', async () => {
        toBlobMock.mockResolvedValue(new Blob(['fake'], { type: 'image/png' }));
        const otherError = new Error('failed');
        otherError.name = 'NotAllowedError';
        vi.stubGlobal('navigator', { ...navigator, canShare: () => true, share: vi.fn().mockRejectedValue(otherError) });

        controllerButton().click();
        await nextTick();
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="workout--share"]'),
            'workout--share',
        );
        await controller.shareFromPreview();

        expect(showErrorToast).toHaveBeenCalledWith('Erreur');
    });
});
