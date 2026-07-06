import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['initials', 'image'];

    connect() {
        this.boundUpdateInitials = this.#updateInitials.bind(this);
        this.boundUpdateAvatar = this.#updateAvatar.bind(this);
        window.addEventListener('user:nickname-updated', this.boundUpdateInitials);
        window.addEventListener('user:avatar-updated', this.boundUpdateAvatar);
    }

    disconnect() {
        window.removeEventListener('user:nickname-updated', this.boundUpdateInitials);
        window.removeEventListener('user:avatar-updated', this.boundUpdateAvatar);
    }

    #updateInitials(event) {
        this.initialsTarget.textContent = event.detail.initials;
    }

    #updateAvatar(event) {
        if (event.detail.url) {
            this.imageTarget.src = event.detail.url;
            this.imageTarget.classList.remove('hidden');
            this.initialsTarget.classList.add('hidden');
            return;
        }

        this.imageTarget.classList.add('hidden');
        this.initialsTarget.classList.remove('hidden');
    }
}
