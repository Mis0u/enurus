import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));
vi.mock('../../../../assets/utils/clipboard.js', () => ({
    copyToClipboard: vi.fn(),
}));

const { showSuccessToast, showErrorToast } = await import('../../../../assets/utils/toast.js');
const { copyToClipboard } = await import('../../../../assets/utils/clipboard.js');
const InvitationController = (await import('../../../../assets/controllers/profile_connection/invitation_controller.js')).default;

const URL_TEMPLATE = 'https://enurus.test/fr/inscription?invitation=__CODE__';

function render({ shareCode = 'ABC123', enabled = true } = {}) {
    document.body.innerHTML = `
        <section data-controller="profile-connection--invitation"
                 data-profile-connection--invitation-url-template-value="${URL_TEMPLATE}"
                 data-profile-connection--invitation-code-placeholder-value="__CODE__"
                 data-profile-connection--invitation-share-code-value="${shareCode}"
                 data-profile-connection--invitation-enabled-value="${enabled}"
                 data-profile-connection--invitation-help-message-value="Envoie ce lien"
                 data-profile-connection--invitation-sharing-required-hint-value="Active le partage"
                 data-profile-connection--invitation-share-text-value="Rejoins-moi"
                 data-profile-connection--invitation-copy-message-value="Lien copié"
                 data-profile-connection--invitation-copy-error-message-value="Erreur de copie">
            <p data-profile-connection--invitation-target="hint"></p>
            <button type="button" data-profile-connection--invitation-target="copyButton" data-action="profile-connection--invitation#copy">Copier</button>
            <button type="button" hidden data-profile-connection--invitation-target="shareButton" data-action="profile-connection--invitation#share">Partager</button>
        </section>
    `;
}

const copyButton = () => document.querySelector('[data-profile-connection--invitation-target="copyButton"]');
const shareButton = () => document.querySelector('[data-profile-connection--invitation-target="shareButton"]');
const hint = () => document.querySelector('[data-profile-connection--invitation-target="hint"]');

describe('profile-connection--invitation controller', () => {
    let application;

    async function start() {
        application = Application.start();
        application.register('profile-connection--invitation', InvitationController);
        await vi.waitFor(() => expect(hint().textContent).not.toBe(''));
    }

    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('copies the invitation link built on the share code', async () => {
        copyToClipboard.mockResolvedValue(true);
        render();
        await start();

        copyButton().click();
        await vi.waitFor(() => expect(showSuccessToast).toHaveBeenCalledWith('Lien copié'));

        expect(copyToClipboard).toHaveBeenCalledWith('https://enurus.test/fr/inscription?invitation=ABC123');
    });

    it('reports a failed copy instead of pretending it worked', async () => {
        copyToClipboard.mockResolvedValue(false);
        render();
        await start();

        copyButton().click();
        await vi.waitFor(() => expect(showErrorToast).toHaveBeenCalledWith('Erreur de copie'));

        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('offers the native share sheet only where the browser has one', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { ...navigator, share });
        render();
        await start();

        expect(shareButton().hidden).toBe(false);

        shareButton().click();
        await vi.waitFor(() => expect(share).toHaveBeenCalled());

        expect(share).toHaveBeenCalledWith({
            text: 'Rejoins-moi',
            url: 'https://enurus.test/fr/inscription?invitation=ABC123',
        });
    });

    it('keeps the share button hidden without a native share sheet', async () => {
        vi.stubGlobal('navigator', { ...navigator, share: undefined });
        render();
        await start();

        expect(shareButton().hidden).toBe(true);
    });

    it('is disabled with a hint while profile sharing is off', async () => {
        render({ enabled: false });
        await start();

        expect(copyButton().disabled).toBe(true);
        expect(shareButton().disabled).toBe(true);
        expect(hint().textContent).toBe('Active le partage');
    });

    it('follows profile sharing being turned on and off', async () => {
        render({ enabled: false });
        await start();

        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', { detail: { isDiscoverable: true } }));

        expect(copyButton().disabled).toBe(false);
        expect(hint().textContent).toBe('Envoie ce lien');

        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', { detail: { isDiscoverable: false } }));

        expect(copyButton().disabled).toBe(true);
    });

    it('follows a regenerated share code', async () => {
        copyToClipboard.mockResolvedValue(true);
        render();
        await start();

        window.dispatchEvent(new CustomEvent('profile-connection:share-code-changed', { detail: { shareCode: 'NEW234' } }));
        copyButton().click();
        await vi.waitFor(() => expect(copyToClipboard).toHaveBeenCalled());

        expect(copyToClipboard).toHaveBeenCalledWith('https://enurus.test/fr/inscription?invitation=NEW234');
    });
});
