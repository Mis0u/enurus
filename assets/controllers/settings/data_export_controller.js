/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { hideSpinner, showSpinner } from '../../utils/submit_spinner.js';
import { showErrorToast } from '../../utils/toast.js';

// Le fichier est préparé en entier côté serveur avant l'envoi : sans retour visuel, un gros
// historique ressemble à un clic sans effet. Le lien reste un vrai lien de téléchargement sans JS.
const FILENAME_PATTERN = /filename="?([^";]+)"?/;
const FALLBACK_FILENAME = 'enurus-workouts';

export default class extends Controller {
    static values = {
        errorMessage: String,
    };

    async download(event) {
        event.preventDefault();
        const link = event.currentTarget;

        if ('true' === link.getAttribute('aria-busy')) {
            return;
        }

        this.#markBusy(link);

        try {
            await this.#fetchAndSave(link.href);
        } catch {
            showErrorToast(this.errorMessageValue);
        } finally {
            this.#markIdle(link);
        }
    }

    async #fetchAndSave(url) {
        const response = await fetch(url);

        // Session expirée : la requête a suivi la redirection vers la connexion, rien à enregistrer.
        if (response.redirected) {
            window.location.assign(response.url);
            return;
        }

        if (!response.ok) {
            throw new Error(`Export failed with status ${response.status}`);
        }

        this.#saveFile(await response.blob(), this.#filenameOf(response));
    }

    #filenameOf(response) {
        const disposition = response.headers.get('Content-Disposition') ?? '';

        return disposition.match(FILENAME_PATTERN)?.[1] ?? FALLBACK_FILENAME;
    }

    #saveFile(blob, filename) {
        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        // Différé : certains navigateurs lisent encore l'URL juste après le clic.
        setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
    }

    #markBusy(link) {
        link.setAttribute('aria-busy', 'true');
        link.setAttribute('aria-disabled', 'true');
        showSpinner(link);
    }

    #markIdle(link) {
        link.removeAttribute('aria-busy');
        link.removeAttribute('aria-disabled');
        hideSpinner(link);
    }
}
