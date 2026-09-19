import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'submit', 'hint'];

    static values = {
        helpMessage: String,
        sharingRequiredHint: String,
    };

    connect() {
        this.onSharingChanged = this.onSharingChanged.bind(this);
        window.addEventListener('profile-connection:sharing-changed', this.onSharingChanged);
    }

    disconnect() {
        window.removeEventListener('profile-connection:sharing-changed', this.onSharingChanged);
    }

    onSharingChanged(event) {
        const { isDiscoverable } = event.detail;

        this.inputTarget.disabled = ! isDiscoverable;
        this.submitTarget.disabled = ! isDiscoverable;
        this.hintTarget.textContent = isDiscoverable ? this.helpMessageValue : this.sharingRequiredHintValue;
    }
}
