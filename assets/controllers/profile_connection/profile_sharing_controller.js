import { Controller } from '@hotwired/stimulus';
import { showSuccessToast, showErrorToast } from '../../utils/toast.js';
import { copyToClipboard } from '../../utils/clipboard.js';

export default class extends Controller {
    static targets = ['code', 'fullCode', 'codeWrapper', 'workoutCheckbox'];

    static values = {
        toggleUrl: String,
        regenerateUrl: String,
        workoutToggleUrl: String,
        toggleToken: String,
        regenerateToken: String,
        workoutToggleToken: String,
        enableMessage: String,
        disableMessage: String,
        regenerateMessage: String,
        copyMessage: String,
        copyErrorMessage: String,
        enableWorkoutsMessage: String,
        disableWorkoutsMessage: String,
    };

    async toggle(event) {
        const isDiscoverable = event.target.checked;

        try {
            const response = await fetch(this.toggleUrlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ isDiscoverable, _token: this.toggleTokenValue }),
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            this.#applyShareCode(data.shareCode);
            this.#applyWorkoutSharing(data.shareWorkouts, isDiscoverable);
            window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
                detail: {
                    isDiscoverable: data.isDiscoverable,
                    shareWorkouts: data.shareWorkouts,
                    hiddenSharedWidgets: data.hiddenSharedWidgets,
                },
            }));
            showSuccessToast(isDiscoverable ? this.enableMessageValue : this.disableMessageValue);
        } catch {
            // Échec silencieux volontaire — même pattern que dashboard_widgets_controller.js.
        }
    }

    async toggleWorkouts(event) {
        const shareWorkouts = event.target.checked;

        try {
            const response = await fetch(this.workoutToggleUrlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ shareWorkouts, _token: this.workoutToggleTokenValue }),
            });

            if (! response.ok) {
                event.target.checked = ! shareWorkouts;

                return;
            }

            const data = await response.json();
            window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
                detail: {
                    isDiscoverable: true,
                    shareWorkouts: data.shareWorkouts,
                    hiddenSharedWidgets: data.hiddenSharedWidgets,
                },
            }));
            showSuccessToast(shareWorkouts ? this.enableWorkoutsMessageValue : this.disableWorkoutsMessageValue);
        } catch {
            event.target.checked = ! shareWorkouts;
        }
    }

    async regenerate() {
        try {
            const response = await fetch(this.regenerateUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: this.regenerateTokenValue }),
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            this.#applyShareCode(data.shareCode);
            showSuccessToast(this.regenerateMessageValue);
        } catch {
            // Échec silencieux volontaire — même pattern que dashboard_widgets_controller.js.
        }
    }

    async copy() {
        const text = this.fullCodeTarget.textContent.trim();

        if (await copyToClipboard(text)) {
            showSuccessToast(this.copyMessageValue);
        } else {
            showErrorToast(this.copyErrorMessageValue);
        }
    }

    // Le code, une fois généré, reste affiché même si le partage est ensuite désactivé
    // (shareCode n'est jamais effacé côté serveur) — jamais de ré-masquage ici.
    #applyShareCode(shareCode) {
        if (! shareCode) {
            return;
        }

        if (this.hasCodeTarget) {
            this.codeTarget.textContent = shareCode;
        }

        if (this.hasCodeWrapperTarget) {
            this.codeWrapperTarget.hidden = false;
        }

        // Le lien d'invitation est construit sur ce code : il doit suivre chaque nouveau code.
        window.dispatchEvent(new CustomEvent('profile-connection:share-code-changed', {
            detail: { shareCode },
        }));
    }

    // Le partage des séances est un opt-in secondaire qui dépend du partage de profil : dès que
    // celui-ci se coupe, la case doit se décocher et se désactiver dans le même geste (le serveur
    // a déjà forcé shareWorkouts à faux en cascade — cf. ProfileConnectionSharingToggleController).
    #applyWorkoutSharing(shareWorkouts, isDiscoverable) {
        if (! this.hasWorkoutCheckboxTarget) {
            return;
        }

        this.workoutCheckboxTarget.checked = shareWorkouts;
        this.workoutCheckboxTarget.disabled = ! isDiscoverable;
    }
}
