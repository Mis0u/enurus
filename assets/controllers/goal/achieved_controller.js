import { Controller } from '@hotwired/stimulus';
import confetti from 'canvas-confetti';
import Swal from 'sweetalert2';
import { enqueueCelebration } from '../../utils/celebration_queue.js';

/**
 * Déclenché une fois par affichage (flash bag lu et vidé côté serveur) — pas de garde
 * anti-double-affichage nécessaire, le message ne réapparaît jamais après un rechargement.
 * Passe par `celebration_queue.js` pour ne jamais écraser une autre popup (badge débloqué).
 */
export default class extends Controller {
    static values = { achievements: Array };

    connect() {
        this.achievementsValue.forEach(achievement => {
            enqueueCelebration(() => this.#celebrate(achievement));
        });
    }

    #celebrate(achievement) {
        confetti({ particleCount: 130, spread: 75, origin: { y: 0.6 } });

        return Swal.fire({
            icon: 'success',
            title: achievement.title,
            text: achievement.text,
            confirmButtonText: achievement.confirmButtonText,
            background: '#0f1928',
            color: '#f0f4ff',
            iconColor: '#22c55e',
            confirmButtonColor: '#f43f5e',
        });
    }
}
