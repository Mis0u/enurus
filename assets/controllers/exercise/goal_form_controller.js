import { Controller } from '@hotwired/stimulus';

/**
 * Objectif défini : le formulaire reste masqué par défaut (barre de progression seule visible),
 * révélé uniquement via le bouton "Modifier" — évite de laisser croire qu'on peut enregistrer
 * un second objectif en plus de l'actuel.
 */
export default class extends Controller {
    static targets = ['form'];

    show() {
        this.formTarget.classList.remove('hidden');
    }
}
