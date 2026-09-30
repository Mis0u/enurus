import { Controller } from '@hotwired/stimulus';
import { captureToBlob, canShareImageFiles, shareImage } from '../../utils/share_image.js';
import { showErrorToast } from '../../utils/toast.js';

// Cadre de 405 × 720 px (9:16) capturé à ×8/3 : image 1080 × 1920, le format story des réseaux
// sociaux.
const PIXEL_RATIO = 1080 / 405;
const TOUCH_SCREEN = '(pointer: coarse)';

// Partage d'un écran du résumé annuel, sur mobile seulement : copie de l'écran courant dans un
// cadre hors-écran habillé (« ENURUS · année » / « enurus.fr »), capture, aperçu, puis feuille de
// partage native au second tap (Safari n'ouvre le partage que juste après un geste de
// l'utilisateur, jamais après une capture asynchrone). Le parcours reste figé pendant ce temps
// (évènements `opened` / `closed`, écoutés par `year-in-review--story`).
export default class extends Controller {
    static targets = ['button', 'frame', 'slot', 'dialog', 'previewImage'];

    static values = {
        fileName: String,
        error: String,
    };

    #blob = null;
    #previewUrl = null;
    #screenType = '';

    connect() {
        if (window.matchMedia(TOUCH_SCREEN).matches && canShareImageFiles()) {
            this.buttonTarget.classList.replace('hidden', 'flex');
        }
    }

    async capture() {
        this.dispatch('opened');

        try {
            this.#copyCurrentScreen();
            this.#openPreview(await captureToBlob(this.frameTarget, PIXEL_RATIO));
        } catch {
            this.reset();
            showErrorToast(this.errorValue);
        }
    }

    async share() {
        try {
            await shareImage(this.#blob, this.fileNameValue.replace('__screen__', this.#screenType));
            this.close();
        } catch (error) {
            if ('AbortError' !== error.name) {
                showErrorToast(this.errorValue);
            }
        }
    }

    close() {
        this.dialogTarget.close();
    }

    // Fermeture du <dialog> (bouton, Échap natif) : on libère l'aperçu et le parcours.
    reset() {
        if (this.#previewUrl) {
            URL.revokeObjectURL(this.#previewUrl);
        }

        this.#previewUrl = null;
        this.#blob = null;
        this.slotTarget.replaceChildren();
        this.dispatch('closed');
    }

    // Copie inerte : sans cible ni contrôleur Stimulus, elle ne perturbe pas le parcours (la
    // silhouette garde ses couleurs, déjà posées en style inline).
    #copyCurrentScreen() {
        const screen = this.element.querySelector('[data-year-in-review--story-target="screen"]:not([hidden])');
        const copy = screen.cloneNode(true);

        copy.removeAttribute('data-year-in-review--story-target');
        copy.querySelectorAll('[data-controller]').forEach((element) => element.removeAttribute('data-controller'));
        this.#screenType = screen.dataset.yearInReviewScreen;
        this.slotTarget.replaceChildren(copy);
    }

    #openPreview(blob) {
        this.#blob = blob;
        this.#previewUrl = URL.createObjectURL(blob);
        this.previewImageTarget.src = this.#previewUrl;
        this.dialogTarget.showModal();
    }
}
