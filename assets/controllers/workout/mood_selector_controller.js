import { Controller } from '@hotwired/stimulus';

/**
 * Chips à icône pour l'humeur de séance (App\Enum\Entity\Workout\WorkoutMoodEnum) — partagé entre
 * la modal de note (création) et la carte d'édition. Pilote un <select> natif toujours masqué
 * (rendu par Symfony via form_widget(), jamais construit ici) : cliquer une chip met à jour sa
 * valeur directement, donc FormData(form) la récupère à la soumission sans injection manuelle
 * de dernière minute (contrairement à la note, saisie dans un textarea déconnecté du formulaire).
 * Champ facultatif, aucune sélection par défaut — cliquer la chip déjà active la désélectionne.
 */
export default class extends Controller {
    static targets = ['select', 'chip'];

    connect() {
        this.#syncChips();
    }

    select(event) {
        const chip = event.currentTarget;
        const value = chip.dataset.moodValue;

        this.selectTarget.value = this.selectTarget.value === value ? '' : value;
        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.#syncChips();
    }

    // ─── Privé ───────────────────────────────────────────────────

    #syncChips() {
        const current = this.selectTarget.value;

        this.chipTargets.forEach((chip) => {
            const isSelected = chip.dataset.moodValue === current;
            chip.classList.toggle('mood-chip--selected', isSelected);
            chip.setAttribute('aria-pressed', String(isSelected));
        });
    }
}
