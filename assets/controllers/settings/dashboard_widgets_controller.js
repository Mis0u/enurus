import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { showSuccessToast } from '../../utils/toast.js';

// Réglages › Widgets du dashboard : afficher/masquer chaque widget, et les réorganiser — à la
// poignée (souris) ou avec ↑ ↓ (tactile, clavier, lecteur d'écran). L'ordre est enregistré à chaque
// déplacement, c'est celui du dashboard.
export default class extends Controller {
    static targets = ['list', 'row', 'moveUpButton', 'moveDownButton'];

    static values = {
        url: String,
        orderUrl: String,
        csrfToken: String,
        successMessage: String,
    };

    connect() {
        if (! this.hasListTarget) {
            return;
        }

        this.sortable = new Sortable(this.listTarget, {
            animation: 250,
            handle: '.drag-handle',
            ghostClass: 'opacity-30',
            chosenClass: 'opacity-50',
            onEnd: () => this.#orderChanged(),
        });
        this.#refreshMoveButtons();
    }

    disconnect() {
        this.sortable?.destroy();
    }

    async toggle(event) {
        const widget = event.params.widget;
        const hidden = !event.target.checked;

        await this.#patch(this.urlValue, { widget, hidden });
    }

    moveUp(event) {
        const row = this.#rowOf(event.currentTarget);
        row.previousElementSibling?.before(row);
        this.#movedWith(event.currentTarget);
    }

    moveDown(event) {
        const row = this.#rowOf(event.currentTarget);
        row.nextElementSibling?.after(row);
        this.#movedWith(event.currentTarget);
    }

    #rowOf(element) {
        return element.closest('[data-settings--dashboard-widgets-target="row"]');
    }

    // Déplacer la ligne dans le DOM fait perdre le focus : on le rend à la flèche utilisée, pour
    // pouvoir la presser de nouveau au clavier.
    #movedWith(button) {
        button.focus();
        this.#orderChanged();
    }

    #orderChanged() {
        this.#refreshMoveButtons();
        this.#patch(this.orderUrlValue, { order: this.rowTargets.map((row) => row.dataset.widget) });
    }

    #refreshMoveButtons() {
        const lastIndex = this.rowTargets.length - 1;

        this.rowTargets.forEach((row, index) => {
            this.#moveButtonOf(row, 'moveUpButton').disabled = 0 === index;
            this.#moveButtonOf(row, 'moveDownButton').disabled = lastIndex === index;
        });
    }

    #moveButtonOf(row, target) {
        return row.querySelector(`[data-settings--dashboard-widgets-target="${target}"]`);
    }

    async #patch(url, payload) {
        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...payload, _token: this.csrfTokenValue }),
            });

            if (response.ok) {
                showSuccessToast(this.successMessageValue);
            }
        } catch {
            // Échec silencieux volontaire — l'absence de toast signale l'échec à l'utilisateur
            // (même pattern que select_field_controller.js).
        }
    }
}
