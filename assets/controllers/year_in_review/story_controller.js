import { Controller } from '@hotwired/stimulus';
import { StoryTimer } from './story_timer.js';

const LONG_PRESS_MS = 300;
const PREVIOUS_ZONE_RATIO = 1 / 3;

// Parcours « Ton année » en écrans façon story : un écran à la fois, minuteur par écran (sauf le
// dernier), tap gauche/droite, boutons, clavier (← → Espace, Échap), pause par bouton (WCAG 2.2.2),
// appui long et onglet caché. Aucun défilement automatique si « réduire les animations » est actif.
// Sans JavaScript, les écrans restent empilés et tous lisibles.
export default class extends Controller {
    static targets = ['screen', 'bar', 'status', 'pauseButton', 'track', 'previousButton', 'nextButton'];

    static values = {
        duration: Number,
        closeUrl: String,
        pauseLabel: String,
        resumeLabel: String,
        statusTemplate: String,
    };

    #index = 0;
    #timer;
    #userPaused = false;
    #reducedMotion = false;
    #pressTimeout = null;
    #holding = false;
    #swallowNextTap = false;
    #suspended = false;
    #onVisibilityChange = () => this.#syncWithVisibility();

    connect() {
        this.#reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.#timer = new StoryTimer({
            duration: this.durationValue,
            onProgress: (progress) => this.#fillBar(this.#index, progress),
            onEnd: () => this.next(),
        });
        document.addEventListener('visibilitychange', this.#onVisibilityChange);
        this.element.dataset.story = 'on';
        this.#show(0);
    }

    disconnect() {
        this.#timer.stop();
        document.removeEventListener('visibilitychange', this.#onVisibilityChange);
        delete this.element.dataset.story;
    }

    next() {
        if (this.#index < this.screenTargets.length - 1) {
            this.#show(this.#index + 1);
        }
    }

    previous() {
        this.#show(Math.max(0, this.#index - 1));
    }

    togglePause() {
        this.#userPaused = ! this.#userPaused;
        this.#renderPauseButton();

        if (this.#userPaused) {
            this.#timer.pause();

            return;
        }

        this.#timer.resume();
    }

    // Aperçu de partage ouvert : le parcours se fige (minuteur, clavier, taps), Échap ne ferme que
    // l'aperçu.
    suspend() {
        this.#suspended = true;
        this.#timer.pause();
    }

    release() {
        this.#suspended = false;
        this.#syncWithVisibility();
    }

    keydown(event) {
        if (this.#suspended) {
            return;
        }

        if ('ArrowRight' === event.key || (' ' === event.key && ! this.#isControl(event.target))) {
            event.preventDefault();
            this.next();
        } else if ('ArrowLeft' === event.key) {
            event.preventDefault();
            this.previous();
        } else if ('Escape' === event.key) {
            window.location.assign(this.closeUrlValue);
        }
    }

    tap(event) {
        if (this.#suspended) {
            return;
        }

        if (this.#swallowNextTap || this.#isControl(event.target)) {
            this.#swallowNextTap = false;

            return;
        }

        const bounds = this.trackTarget.getBoundingClientRect();
        const isPreviousZone = event.clientX - bounds.left < bounds.width * PREVIOUS_ZONE_RATIO;

        isPreviousZone ? this.previous() : this.next();
    }

    pressStart(event) {
        if (this.#suspended || this.#isControl(event.target)) {
            return;
        }

        this.#pressTimeout = setTimeout(() => {
            this.#holding = true;
            this.#timer.pause();
        }, LONG_PRESS_MS);
    }

    pressEnd() {
        clearTimeout(this.#pressTimeout);

        if (! this.#holding) {
            return;
        }

        this.#holding = false;
        this.#swallowNextTap = true;

        if (! this.#userPaused) {
            this.#timer.resume();
        }
    }

    #show(index) {
        this.#index = index;
        this.screenTargets.forEach((screen, position) => {
            screen.hidden = position !== index;
        });
        this.barTargets.forEach((bar, position) => this.#fillBar(position, position < index ? 1 : 0));
        this.statusTarget.textContent = this.statusTemplateValue
            .replace('__position__', String(index + 1))
            .replace('__count__', String(this.screenTargets.length));
        this.screenTargets[index].focus({ preventScroll: true });
        this.#renderNavigationButtons();
        this.#startTimer();
    }

    #startTimer() {
        const isLastScreen = this.#index === this.screenTargets.length - 1;

        if (isLastScreen || this.#reducedMotion) {
            this.#timer.stop();
            this.#fillBar(this.#index, 1);

            return;
        }

        this.#timer.start();

        if (this.#userPaused || document.hidden) {
            this.#timer.pause();
        }
    }

    #syncWithVisibility() {
        if (document.hidden || this.#suspended) {
            this.#timer.pause();
        } else if (! this.#userPaused) {
            this.#timer.resume();
        }
    }

    #fillBar(position, progress) {
        const bar = this.barTargets[position];

        if (bar) {
            bar.style.width = `${progress * 100}%`;
        }
    }

    // Rien avant le premier écran ni après le dernier, et pas de pause sans minuteur : ces boutons
    // y sont masqués sans décaler la mise en page (visibility plutôt que display).
    #renderNavigationButtons() {
        const isFirst = 0 === this.#index;
        const isLast = this.#index === this.screenTargets.length - 1;

        this.previousButtonTargets.forEach((button) => { button.style.visibility = isFirst ? 'hidden' : ''; });
        this.nextButtonTargets.forEach((button) => { button.style.visibility = isLast ? 'hidden' : ''; });
        this.pauseButtonTarget.style.visibility = isLast || this.#reducedMotion ? 'hidden' : '';
    }

    #renderPauseButton() {
        this.pauseButtonTarget.setAttribute('aria-pressed', String(this.#userPaused));
        this.pauseButtonTarget.setAttribute('aria-label', this.#userPaused ? this.resumeLabelValue : this.pauseLabelValue);
    }

    // Un lien ou un bouton garde son propre comportement (clic, Espace) ; la cible d'un évènement
    // clavier global peut être `window`, qui n'est pas un élément.
    #isControl(target) {
        return target instanceof Element && null !== target.closest('a, button');
    }
}
