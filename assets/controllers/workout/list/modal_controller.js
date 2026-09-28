import { Controller } from '@hotwired/stimulus';

/**
 * Ouverture/fermeture d'une modale de l'onglet Calendrier (semaine de repos, objectif de régularité)
 * — une instance par carte, même pattern que la modal de note en création (classList hidden/flex),
 * pas de logique métier ici, juste l'affichage.
 */
export default class extends Controller {
    static targets = ['modal'];

    open() {
        this.modalTarget.classList.remove('hidden');
        this.modalTarget.classList.add('flex');
    }

    close() {
        this.modalTarget.classList.add('hidden');
        this.modalTarget.classList.remove('flex');
    }
}
