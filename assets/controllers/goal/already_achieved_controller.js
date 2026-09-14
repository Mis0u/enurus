import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

/**
 * Déclenché une fois par affichage (flash bag lu et vidé côté serveur) — pas de garde
 * anti-double-affichage nécessaire, le message ne réapparaît jamais après un rechargement.
 * Pas de confettis ici : contrairement à `goal/achieved_controller.js`, aucun nouvel
 * accomplissement n'a eu lieu — on informe seulement que la cible est déjà derrière soi.
 */
export default class extends Controller {
    static values = { achievements: Array };

    connect() {
        this.achievementsValue.forEach((achievement, index) => {
            window.setTimeout(() => this.#inform(achievement), index * 700);
        });
    }

    #inform(achievement) {
        Swal.fire({
            icon: 'info',
            title: achievement.title,
            text: achievement.text,
            confirmButtonText: achievement.confirmButtonText,
            background: '#0f1928',
            color: '#f0f4ff',
            iconColor: '#06b6d4',
            confirmButtonColor: '#f43f5e',
        });
    }
}
