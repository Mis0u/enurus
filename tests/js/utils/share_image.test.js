import { afterEach, describe, expect, it, vi } from 'vitest';

const { toBlobMock } = vi.hoisted(() => ({ toBlobMock: vi.fn() }));

vi.mock('html-to-image', () => ({ toBlob: toBlobMock }), { virtual: true });

const { captureToBlob, canShareImageFiles, shareImage } = await import('../../../assets/utils/share_image.js');

describe('share_image', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        toBlobMock.mockReset();
    });

    it('captures the node back on screen at the requested resolution', async () => {
        const blob = new Blob(['png']);
        toBlobMock.mockResolvedValue(blob);
        const node = document.createElement('div');

        await expect(captureToBlob(node, 3)).resolves.toBe(blob);
        expect(toBlobMock).toHaveBeenCalledWith(node, { pixelRatio: 3, style: { position: 'static', left: '0', top: '0' } });
    });

    it('fails loudly when the capture returns nothing', async () => {
        toBlobMock.mockResolvedValue(null);

        await expect(captureToBlob(document.createElement('div'), 2)).rejects.toThrow();
    });

    it('knows whether the browser can share image files', () => {
        vi.stubGlobal('navigator', { canShare: vi.fn().mockReturnValue(true) });
        expect(canShareImageFiles()).toBe(true);

        vi.stubGlobal('navigator', {});
        expect(canShareImageFiles()).toBe(false);
    });

    it('shares the image as a named PNG file', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { share });

        await shareImage(new Blob(['png']), 'enurus.png');

        const [{ files: [file] }] = share.mock.calls[0];
        expect(file.name).toBe('enurus.png');
        expect(file.type).toBe('image/png');
    });
});
