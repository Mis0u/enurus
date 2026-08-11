// assets/controllers/workout/share_controller.js
//
// Génère une image récapitulative de la séance (carte hors-écran capturée via html-to-image),
// l'affiche dans une modale d'aperçu, et laisse l'utilisateur la partager via l'API native du
// navigateur ou la télécharger directement.

import { Controller } from '@hotwired/stimulus';
import { toBlob } from 'html-to-image';
import { showErrorToast, showSuccessToast } from '../../utils/toast.js';

const CAPTURE_PIXEL_RATIO = 2;
const FILE_NAME = 'enurus-workout.png';

export default class extends Controller {
    static targets = ['card', 'button', 'modal', 'previewImage', 'shareButton', 'caption'];

    static values = {
        downloadSuccess: String,
        error: String,
        captionShare: String,
        captionDownloadOnly: String,
    };

    #blob = null;
    #previewUrl = null;
    #loaderEl = null;

    async share() {
        this.#showLoader();

        try {
            const blob = await this.#captureCard();
            this.#openPreview(blob);
        } catch {
            showErrorToast(this.errorValue);
        } finally {
            this.#hideLoader();
        }
    }

    closePreview() {
        this.modalTarget.classList.add('hidden');
        this.modalTarget.classList.remove('flex');

        if (this.#previewUrl) {
            URL.revokeObjectURL(this.#previewUrl);
            this.#previewUrl = null;
        }

        this.#blob = null;
    }

    stopPropagation(event) {
        event.stopPropagation();
    }

    async shareFromPreview() {
        if (!this.#blob) {
            return;
        }

        try {
            const file = new File([this.#blob], FILE_NAME, { type: 'image/png' });
            await navigator.share({ files: [file] });
            this.closePreview();
        } catch (error) {
            if ('AbortError' !== error.name) {
                showErrorToast(this.errorValue);
            }
        }
    }

    downloadFromPreview() {
        if (!this.#blob) {
            return;
        }

        const url = URL.createObjectURL(this.#blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = FILE_NAME;
        link.click();

        URL.revokeObjectURL(url);
        showSuccessToast(this.downloadSuccessValue);
        this.closePreview();
    }

    async #captureCard() {
        // La carte est cachée hors-écran via position:fixed + un offset négatif énorme
        // (voir _share_card.html.twig). html-to-image clone le nœud en conservant son style
        // inline tel quel — sans ce reset, l'offset se retrouve appliqué à l'intérieur même
        // de l'image capturée et pousse tout le contenu hors cadre (image vide/décalée).
        const blob = await toBlob(this.cardTarget, {
            pixelRatio: CAPTURE_PIXEL_RATIO,
            style: { position: 'static', left: '0', top: '0' },
        });

        if (!blob) {
            throw new Error('Image capture returned no data.');
        }

        return blob;
    }

    #openPreview(blob) {
        const canShare = this.#canShareFiles();

        this.#blob = blob;
        this.#previewUrl = URL.createObjectURL(blob);
        this.previewImageTarget.src = this.#previewUrl;
        this.shareButtonTarget.hidden = !canShare;
        // Sans navigator.share/canShare pour les fichiers (desktop, Firefox mobile — l'API ne
        // supporte que le partage de texte/URL là-bas, jamais de fichier), la légende explique
        // qu'il faut télécharger puis partager manuellement, plutôt que de disparaître sans
        // explication.
        this.captionTarget.textContent = canShare ? this.captionShareValue : this.captionDownloadOnlyValue;
        this.modalTarget.classList.remove('hidden');
        this.modalTarget.classList.add('flex');
    }

    #canShareFiles() {
        const probe = new File([], FILE_NAME, { type: 'image/png' });

        return Boolean(navigator.canShare?.({ files: [probe] }));
    }

    // Loader visuel — Tailwind pur (animate-spin natif, pas de CSS custom),
    // même pattern que workout--routine-loader.
    #showLoader() {
        this.buttonTarget.disabled = true;
        this.buttonTarget.classList.add('opacity-50', 'cursor-wait');

        this.#loaderEl = document.createElement('span');
        this.#loaderEl.className = 'inline-block w-4 h-4 border-2 border-rose-500/20 border-t-rose-500 rounded-full animate-spin';
        this.#loaderEl.setAttribute('aria-hidden', 'true');
        this.buttonTarget.appendChild(this.#loaderEl);
    }

    #hideLoader() {
        this.buttonTarget.disabled = false;
        this.buttonTarget.classList.remove('opacity-50', 'cursor-wait');
        this.#loaderEl?.remove();
        this.#loaderEl = null;
    }
}
