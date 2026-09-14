import { Controller } from '@hotwired/stimulus';
import confetti from 'canvas-confetti';
import Swal from 'sweetalert2';

/**
 * Déclenché une fois par affichage (flash bag lu et vidé côté serveur) — pas de garde
 * anti-double-affichage nécessaire, le message ne réapparaît jamais après un rechargement.
 */
export default class extends Controller {
    static values = { achievements: Array };

    connect() {
        this.achievementsValue.forEach((achievement, index) => {
            window.setTimeout(() => this.#celebrate(achievement), index * 700);
        });
    }

    #celebrate(achievement) {
        confetti({ particleCount: 130, spread: 75, origin: { y: 0.6 } });

        Swal.fire({
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
