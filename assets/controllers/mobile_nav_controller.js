import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'panel'];

    open() {
        this.overlayTarget.classList.remove('hidden');
        // Force a reflow so the browser registers the translated state before
        // removing it — otherwise both class changes can land in the same
        // paint and the slide-in transition never plays.
        void this.panelTarget.offsetWidth;
        this.panelTarget.classList.remove('-translate-x-full');
    }

    close() {
        this.panelTarget.classList.add('-translate-x-full');
        setTimeout(() => this.overlayTarget.classList.add('hidden'), 300);
    }

    stop(event) {
        event.stopPropagation();
    }
}
