import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';
import { enqueueCelebration } from '../../utils/celebration_queue.js';

/**
 * Déclenché une fois par affichage (flash bag lu et vidé côté serveur) — pas de garde
 * anti-double-affichage nécessaire, le message ne réapparaît jamais après un rechargement.
 * Pas de confettis ici : contrairement à `goal/achieved_controller.js`, aucun nouvel
 * accomplissement n'a eu lieu — on informe seulement que la cible est déjà derrière soi.
 */
export default class extends Controller {
    static values = { achievements: Array };

    connect() {
        this.achievementsValue.forEach(achievement => {
            enqueueCelebration(() => this.#inform(achievement));
        });
    }

    #inform(achievement) {
        return Swal.fire({
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
