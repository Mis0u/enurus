// Sélection multiple du sélecteur d'exercices (LiveComponent ExerciseSelectorComponent). Les
// cases sont liées à la LiveProp `selectedIds` en `norender` : cocher ne provoque aucun rendu
// serveur, donc ce controller tient à jour, côté client, le libellé « Ajouter (N) » et la
// synchronisation d'un exercice affiché deux fois (« Tes habituels » + liste complète).
//
// Le compte part de `selectedIds` rendu par le serveur, jamais des cases présentes dans le DOM :
// une recherche ou un filtre masque des exercices cochés, qui doivent rester comptés.

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['addButton'];

    static values = {
        selectedIds: Array,
        addLabel: String,
    };

    #selectedIds = new Set();

    selectedIdsValueChanged() {
        this.#selectedIds = new Set(this.selectedIdsValue);
        this.#renderAddButton();
    }

    toggle(event) {
        const { value, checked } = event.target;

        if (checked) {
            this.#selectedIds.add(value);
        } else {
            this.#selectedIds.delete(value);
        }

        this.#syncTwinCheckboxes(value, checked);
        this.#renderAddButton();
    }

    #syncTwinCheckboxes(value, checked) {
        this.element.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            if (checkbox.value === value) {
                checkbox.checked = checked;
            }
        });
    }

    #renderAddButton() {
        if (!this.hasAddButtonTarget) {
            return;
        }

        this.addButtonTarget.textContent = this.addLabelValue.replace('__COUNT__', this.#selectedIds.size);
        this.addButtonTarget.disabled = this.#selectedIds.size === 0;
    }
}
