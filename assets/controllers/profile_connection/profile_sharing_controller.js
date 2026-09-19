import { Controller } from '@hotwired/stimulus';
import { showSuccessToast, showErrorToast } from '../../utils/toast.js';

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

        if (await this.#writeToClipboard(text)) {
            showSuccessToast(this.copyMessageValue);
        } else {
            showErrorToast(this.copyErrorMessageValue);
        }
    }

    // Observé en pratique : navigator.clipboard.writeText() peut résoudre sans lever d'erreur
    // tout en n'écrivant rien (permission accordée de façon incohérente selon le contexte) —
    // execCommand('copy') sur un textarea temporaire, synchrone et exécuté dans le même geste
    // utilisateur que le clic, est essayé en premier car plus fiable ici ; la Clipboard API
    // moderne ne sert que de repli si execCommand est indisponible.
    async #writeToClipboard(text) {
        if (this.#legacyCopy(text)) {
            return true;
        }

        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);

                return true;
            } catch {
                return false;
            }
        }

        return false;
    }

    #legacyCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        let succeeded = false;

        try {
            succeeded = document.execCommand('copy');
        } catch {
            succeeded = false;
        }

        document.body.removeChild(textarea);

        return succeeded;
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
