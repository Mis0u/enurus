import { Controller } from '@hotwired/stimulus';
import confetti from 'canvas-confetti';
import Swal from 'sweetalert2';
import { enqueueCelebration } from '../../utils/celebration_queue.js';

const LEGEND_CONFETTI_COLORS = ['#f43f5e', '#fb7185', '#a855f7', '#22d3ee', '#06b6d4', '#ffffff'];

/**
 * Popup de badge(s) débloqué(s), déclenchée une fois par affichage (flash bag lu et vidé côté
 * serveur). Le visuel des badges est rendu par Twig dans le `<template>` cible ; le texte passe
 * par `textContent`, jamais par du HTML concaténé. Passe par `celebration_queue.js` pour ne jamais
 * écraser une popup d'objectif atteint.
 */
export default class extends Controller {
    static targets = ['content'];

    static values = {
        title: String,
        text: String,
        seeAllUrl: String,
        seeAllLabel: String,
        closeLabel: String,
        legend: { type: Boolean, default: false },
    };

    connect() {
        enqueueCelebration(() => this.#celebrate());
    }

    async #celebrate() {
        this.#throwConfetti();

        const result = await Swal.fire({
            title: this.titleValue,
            html: this.#buildBody(),
            showCancelButton: true,
            confirmButtonText: this.seeAllLabelValue,
            cancelButtonText: this.closeLabelValue,
            background: '#0f1928',
            color: '#f0f4ff',
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#1a2436',
        });

        if (result?.isConfirmed) {
            window.location.assign(this.seeAllUrlValue);
        }
    }

    #buildBody() {
        const body = document.createElement('div');
        body.append(this.contentTarget.content.cloneNode(true));

        const text = document.createElement('p');
        text.textContent = this.textValue;
        body.append(text);

        return body;
    }

    #throwConfetti() {
        if (this.legendValue) {
            confetti({ particleCount: 220, spread: 110, startVelocity: 45, origin: { y: 0.55 }, colors: LEGEND_CONFETTI_COLORS });

            return;
        }

        confetti({ particleCount: 130, spread: 75, origin: { y: 0.6 } });
    }
}
