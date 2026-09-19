import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['checkbox'];

    static values = {
        url: String,
        csrfToken: String,
        successMessage: String,
    };

    connect() {
        this.onSharingChanged = this.#onSharingChanged.bind(this);
        window.addEventListener('profile-connection:sharing-changed', this.onSharingChanged);
    }

    disconnect() {
        window.removeEventListener('profile-connection:sharing-changed', this.onSharingChanged);
    }

    async toggle(event) {
        const widget = event.params.widget;
        const hiddenForShare = !event.target.checked;

        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ widget, hiddenForShare, _token: this.csrfTokenValue }),
            });

            if (! response.ok) {
                event.target.checked = ! event.target.checked;

                return;
            }

            showSuccessToast(this.successMessageValue);
        } catch {
            // Échec silencieux volontaire — même pattern que dashboard_widgets_controller.js.
        }
    }

    // Le partage de profil et le partage des séances sont deux verrous en cascade sur l'affichage
    // des widgets : dès que l'un des deux se coupe, chaque case doit se décocher et se désactiver
    // dans le même geste (le serveur a déjà forcé hiddenSharedWidgets en cascade — cf.
    // ProfileConnectionSharingToggleController / ProfileConnectionWorkoutSharingToggleController).
    #onSharingChanged(event) {
        const { isDiscoverable, shareWorkouts, hiddenSharedWidgets } = event.detail;
        const locked = ! isDiscoverable || ! shareWorkouts;

        this.checkboxTargets.forEach((checkbox) => {
            const widget = checkbox.getAttribute('data-profile-connection--shared-widgets-widget-param');

            checkbox.disabled = locked;
            checkbox.checked = ! locked && ! (hiddenSharedWidgets ?? []).includes(widget);
        });
    }
}
