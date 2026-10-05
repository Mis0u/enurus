import { Controller } from '@hotwired/stimulus';
import { showSuccessToast, showErrorToast } from '../../utils/toast.js';
import { copyToClipboard } from '../../utils/clipboard.js';

// Lien d'invitation = URL d'inscription portant le code de partage. Suit en direct le partage
// de profil (sans lui, le lien ne mène à personne) et chaque nouveau code, tous deux pilotés par
// profile_sharing_controller.js.
export default class extends Controller {
    static targets = ['copyButton', 'shareButton', 'hint'];

    static values = {
        urlTemplate: String,
        codePlaceholder: String,
        shareCode: String,
        enabled: Boolean,
        helpMessage: String,
        sharingRequiredHint: String,
        shareText: String,
        copyMessage: String,
        copyErrorMessage: String,
    };

    connect() {
        this.onSharingChanged = this.onSharingChanged.bind(this);
        this.onShareCodeChanged = this.onShareCodeChanged.bind(this);
        window.addEventListener('profile-connection:sharing-changed', this.onSharingChanged);
        window.addEventListener('profile-connection:share-code-changed', this.onShareCodeChanged);

        this.shareButtonTarget.hidden = typeof navigator.share !== 'function';
        this.#render();
    }

    disconnect() {
        window.removeEventListener('profile-connection:sharing-changed', this.onSharingChanged);
        window.removeEventListener('profile-connection:share-code-changed', this.onShareCodeChanged);
    }

    onSharingChanged(event) {
        this.enabledValue = event.detail.isDiscoverable;
        this.#render();
    }

    onShareCodeChanged(event) {
        this.shareCodeValue = event.detail.shareCode;
        this.#render();
    }

    async copy() {
        if (await copyToClipboard(this.#invitationUrl())) {
            showSuccessToast(this.copyMessageValue);
        } else {
            showErrorToast(this.copyErrorMessageValue);
        }
    }

    async share() {
        try {
            await navigator.share({ text: this.shareTextValue, url: this.#invitationUrl() });
        } catch {
            // Partage annulé par l'utilisateur (AbortError) : rien à signaler.
        }
    }

    #invitationUrl() {
        return this.urlTemplateValue.replace(this.codePlaceholderValue, this.shareCodeValue);
    }

    #render() {
        const usable = this.enabledValue && this.shareCodeValue !== '';

        this.copyButtonTarget.disabled = ! usable;
        this.shareButtonTarget.disabled = ! usable;
        this.hintTarget.textContent = usable ? this.helpMessageValue : this.sharingRequiredHintValue;
    }
}
