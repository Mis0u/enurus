/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.min.css';

// Tour guidé du dashboard. Chaque étape vise l'élément `data-tour-step` réellement affiché : la
// cible existe en double (sidebar desktop, barre du bas mobile), une seule est visible selon l'écran.
// Sans cible visible, driver.js centre la bulle.

// Posés par driver.js sur l'élément mis en avant, puis supprimés (pas restaurés) à la fin du tour :
// sans sauvegarde, le bouton « Plus » perdrait son `aria-controls` vers son panneau.
const ARIA_ATTRIBUTES_OVERWRITTEN_BY_DRIVER = ['aria-controls', 'aria-expanded', 'aria-haspopup'];

export default class extends Controller {
    static values = {
        steps: Array,
        labels: Object,
        finishLabel: String,
        finishUrl: String,
    };

    #ariaSnapshots = new Map();

    connect() {
        this.tour = driver({
            showProgress: true,
            progressText: this.labelsValue.progress,
            nextBtnText: this.labelsValue.next,
            prevBtnText: this.labelsValue.previous,
            doneBtnText: this.finishLabelValue,
            popoverClass: 'guided-tour-popover',
            // driver.js pose `aria-label="Close"` en dur sur sa croix.
            onPopoverRender: (popover) => popover.closeButton.setAttribute('aria-label', this.labelsValue.close),
            // Seul hook toujours appelé à la fermeture (croix, Échap, clic à côté) : `onDestroyed`
            // est sauté si l'étape en cours n'a pas fini son animation (clics rapides sur « Suivant »).
            // Le définir oblige à fermer le tour soi-même.
            onDestroyStarted: () => this.#close(),
            steps: this.stepsValue.map((step, index) => this.#toDriverStep(step, index)),
        });
        this.tour.drive();
    }

    disconnect() {
        this.tour?.destroy();
    }

    #toDriverStep({ target, title, description }, index) {
        const element = this.#visibleTarget(target);
        const isLastStep = index === this.stepsValue.length - 1;

        return {
            ...(element && { element }),
            popover: {
                title,
                description,
                ...(isLastStep && { onNextClick: () => this.#goToWorkoutForm() }),
            },
        };
    }

    #visibleTarget(target) {
        const element = [...document.querySelectorAll(`[data-tour-step="${target}"]`)]
            .find((candidate) => candidate.getClientRects().length > 0);

        if (element) {
            this.#saveAriaAttributes(element);
        }

        return element;
    }

    #close() {
        this.tour.destroy();
        // Frame suivante : une animation de changement d'étape encore en cours retirerait sinon ces
        // attributs juste après leur restauration.
        requestAnimationFrame(() => this.#restoreAriaAttributes());
    }

    #saveAriaAttributes(element) {
        this.#ariaSnapshots.set(element, ARIA_ATTRIBUTES_OVERWRITTEN_BY_DRIVER.map((name) => [name, element.getAttribute(name)]));
    }

    #restoreAriaAttributes() {
        this.#ariaSnapshots.forEach((attributes, element) => {
            attributes.forEach(([name, value]) => (null === value ? element.removeAttribute(name) : element.setAttribute(name, value)));
        });
    }

    #goToWorkoutForm() {
        this.tour.destroy();
        window.location.assign(this.finishUrlValue);
    }
}
