import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const { toBlobMock } = vi.hoisted(() => ({ toBlobMock: vi.fn() }));

vi.mock('html-to-image', () => ({ toBlob: toBlobMock }), { virtual: true });
vi.mock('../../../../assets/utils/toast.js', () => ({ showErrorToast: vi.fn(), showSuccessToast: vi.fn() }));

const { showErrorToast } = await import('../../../../assets/utils/toast.js');
const ShareController = (await import('../../../../assets/controllers/year_in_review/share_controller.js')).default;

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

// jsdom n'implémente pas l'ouverture modale d'un <dialog>.
function stubDialog() {
    HTMLDialogElement.prototype.showModal = function showModal() {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function close() {
        this.open = false;
        this.dispatchEvent(new Event('close'));
    };
}

function environment({ touch = true, canShare = true, share = vi.fn().mockResolvedValue(undefined) } = {}) {
    window.matchMedia = vi.fn().mockReturnValue({ matches: touch });
    vi.stubGlobal('navigator', { canShare: vi.fn().mockReturnValue(canShare), share });

    return share;
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="year-in-review--share"
             data-year-in-review--share-file-name-value="enurus-2026-__screen__.png"
             data-year-in-review--share-error-value="Erreur">
            <button class="hidden" data-year-in-review--share-target="button" data-action="year-in-review--share#capture"></button>
            <main>
                <section data-year-in-review-screen="intro" data-year-in-review--story-target="screen" hidden>Intro</section>
                <section data-year-in-review-screen="tonnage" data-year-in-review--story-target="screen">
                    1293 t <div data-controller="workout--show--muscles"></div>
                </section>
            </main>
            <div data-year-in-review--share-target="frame"><div data-year-in-review--share-target="slot"></div></div>
            <dialog data-year-in-review--share-target="dialog" data-action="close->year-in-review--share#reset">
                <img data-year-in-review--share-target="previewImage" alt="">
                <button class="send" data-action="year-in-review--share#share"></button>
                <button class="cancel" data-action="year-in-review--share#close"></button>
            </dialog>
        </div>
    `;
}

const button = () => document.querySelector('[data-year-in-review--share-target="button"]');
const dialog = () => document.querySelector('dialog');
const slot = () => document.querySelector('[data-year-in-review--share-target="slot"]');

describe('year-in-review--share controller', () => {
    let application;
    let events;

    async function start(options) {
        const share = environment(options);
        buildDom();
        events = [];
        const root = document.querySelector('[data-controller]');
        root.addEventListener('year-in-review--share:opened', () => events.push('opened'));
        root.addEventListener('year-in-review--share:closed', () => events.push('closed'));
        application = Application.start();
        application.register('year-in-review--share', ShareController);
        await nextTick();

        return share;
    }

    beforeEach(() => {
        vi.clearAllMocks();
        stubDialog();
        toBlobMock.mockReset().mockResolvedValue(new Blob(['png']));
        global.URL.createObjectURL = vi.fn(() => 'blob:preview');
        global.URL.revokeObjectURL = vi.fn();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('offers sharing on a touch screen able to share files', async () => {
        await start();

        expect(button().classList.contains('flex')).toBe(true);
        expect(button().classList.contains('hidden')).toBe(false);
    });

    it('offers nothing on desktop, even when the browser could share', async () => {
        await start({ touch: false });

        expect(button().classList.contains('hidden')).toBe(true);
    });

    it('offers nothing when files cannot be shared', async () => {
        await start({ canShare: false });

        expect(button().classList.contains('hidden')).toBe(true);
    });

    it('captures a copy of the current screen only, without story wiring, then previews it', async () => {
        await start();

        button().click();
        await vi.waitFor(() => expect(dialog().open).toBe(true));

        const copy = slot().querySelector('section');
        expect(copy.dataset.yearInReviewScreen).toBe('tonnage');
        expect(copy.hasAttribute('data-year-in-review--story-target')).toBe(false);
        expect(copy.querySelector('[data-controller]')).toBeNull();
        expect(toBlobMock).toHaveBeenCalledWith(document.querySelector('[data-year-in-review--share-target="frame"]'), expect.objectContaining({ pixelRatio: 1080 / 405 }));
        expect(document.querySelector('img').src).toBe('blob:preview');
        expect(events).toEqual(['opened']);
    });

    it('shares the preview as a file named after the screen, then closes and releases the story', async () => {
        const share = await start();
        button().click();
        await vi.waitFor(() => expect(dialog().open).toBe(true));

        document.querySelector('.send').click();
        await vi.waitFor(() => expect(dialog().open).toBe(false));

        expect(share.mock.calls[0][0].files[0].name).toBe('enurus-2026-tonnage.png');
        expect(slot().children).toHaveLength(0);
        expect(events).toEqual(['opened', 'closed']);
    });

    it('stays silent when the share sheet is dismissed', async () => {
        const abort = Object.assign(new Error('cancelled'), { name: 'AbortError' });
        await start({ share: vi.fn().mockRejectedValue(abort) });
        button().click();
        await vi.waitFor(() => expect(dialog().open).toBe(true));

        document.querySelector('.send').click();
        await nextTick();

        expect(showErrorToast).not.toHaveBeenCalled();
    });

    it('warns and releases the story when the capture fails', async () => {
        toBlobMock.mockRejectedValue(new Error('canvas'));
        await start();

        button().click();
        await vi.waitFor(() => expect(showErrorToast).toHaveBeenCalledWith('Erreur'));

        expect(dialog().open).toBeFalsy();
        expect(events).toEqual(['opened', 'closed']);
    });
});
