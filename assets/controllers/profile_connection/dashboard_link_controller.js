import { Controller } from '@hotwired/stimulus';
import { showInfoModal } from '../../utils/info_modal.js';

export default class extends Controller {
    static values = {
        blocked: Boolean,
        title: String,
        body: String,
        confirmLabel: String,
    };

    connect() {
        this.onSharingChanged = this.onSharingChanged.bind(this);
        window.addEventListener('profile-connection:sharing-changed', this.onSharingChanged);
    }

    disconnect() {
        window.removeEventListener('profile-connection:sharing-changed', this.onSharingChanged);
    }

    onSharingChanged(event) {
        this.blockedValue = ! event.detail.isDiscoverable;
    }

    guard(event) {
        if (! this.blockedValue) {
            return;
        }

        event.preventDefault();
        showInfoModal({
            title: this.titleValue,
            body: this.bodyValue,
            confirmLabel: this.confirmLabelValue,
        });
    }
}
