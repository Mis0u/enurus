import { Controller } from '@hotwired/stimulus';

/**
 * Bulle d'info au tap — le `title` HTML natif ne se déclenche qu'au survol souris, aucun
 * navigateur mobile ne l'affiche au tap. Ce contrôleur ajoute une bulle personnalisée sur clic,
 * sans rien changer au comportement desktop (le `title` natif reste actif au survol).
 *
 * Positionnée en `fixed` et attachée à `document.body` (pas à `this.element`) car plusieurs
 * cartes du dashboard portent `overflow-hidden` — une bulle en position `absolute` dans le flux
 * normal y serait rognée dès qu'elle dépasse la boîte de la carte.
 */
export default class extends Controller {
    static values = {
        text: String,
    };

    #bubble = null;

    disconnect() {
        this.#hide();
    }

    toggle(event) {
        event.stopPropagation();

        if (this.#bubble) {
            this.#hide();
            return;
        }

        this.#show();
        document.addEventListener('click', this.#onOutsideClick, { once: true });
        window.addEventListener('scroll', this.#hide, { once: true, capture: true });
    }

    #onOutsideClick = () => {
        this.#hide();
    };

    #show() {
        const bubble = document.createElement('span');
        bubble.textContent = this.textValue;
        bubble.className = 'fixed px-2.5 py-1.5 rounded-lg bg-[#0f1928] border border-white/[0.1] text-[#f0f4ff] text-[11px] font-medium whitespace-normal break-words text-center max-w-[min(80vw,320px)] shadow-lg z-[1000] pointer-events-none';

        document.body.appendChild(bubble);
        this.#bubble = bubble;
        this.#position(bubble);
    }

    #position(bubble) {
        const margin = 8;
        const anchor = this.element.getBoundingClientRect();
        const bubbleRect = bubble.getBoundingClientRect();

        let top = anchor.top - bubbleRect.height - margin;
        if (top < margin) {
            top = anchor.bottom + margin;
        }

        let left = anchor.left + anchor.width / 2 - bubbleRect.width / 2;
        left = Math.min(Math.max(left, margin), window.innerWidth - bubbleRect.width - margin);

        bubble.style.top = `${top}px`;
        bubble.style.left = `${left}px`;
    }

    #hide = () => {
        this.#bubble?.remove();
        this.#bubble = null;
        document.removeEventListener('click', this.#onOutsideClick);
        window.removeEventListener('scroll', this.#hide, { capture: true });
    };
}
