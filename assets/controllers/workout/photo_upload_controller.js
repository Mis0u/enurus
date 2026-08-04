import { Controller } from '@hotwired/stimulus';
import { sendDeleteRequest } from '../../utils/delete_confirmation.js';

export default class extends Controller {
    static targets = [
        'zone', 'input', 'preview', 'previewWrapper', 'placeholder', 'error', 'filename', 'lightbox', 'lightboxImg',
        'existingPhotoBlock', 'addPromptBlock',
    ];

    static values = {
        uploadUrl: { type: String, default: '' },
        deleteUrl: { type: String, default: '' },
        deleteCsrfToken: { type: String, default: '' },
        hasExistingPhoto: { type: Boolean, default: false },
        maxSize: { type: Number, default: 5242880 }, // 5 Mo
        acceptedTypes: { type: Array, default: ['image/jpeg', 'image/png', 'image/webp'] },
    };

    #selectedFile = null;

    // Retirer une photo déjà enregistrée (édition) ne doit rien envoyer au serveur tout de suite —
    // seulement au moment où le formulaire est réellement soumis (cf. commitPendingChanges()),
    // sinon "Annuler" laisse la suppression appliquée malgré tout (bug corrigé ici).
    #pendingDelete = false;

    connect() {
        this.#preventDefaultDragBehavior();
    }

    // ─── Drag & Drop ──────────────────────────────────────────────

    onDragOver(event) {
        event.preventDefault();
        this.zoneTarget.classList.add('drag-over');
    }

    onDragLeave() {
        this.zoneTarget.classList.remove('drag-over');
    }

    onDrop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('drag-over');

        const file = event.dataTransfer?.files?.[0];
        if (file) {
            this.#handleFile(file);
        }
    }

    // ─── Sélection via input ──────────────────────────────────────

    onFileChange(event) {
        const file = event.target.files?.[0];
        if (file) {
            this.#handleFile(file);
        }
    }

    // ─── Suppression preview ─────────────────────────────────────

    onRemove() {
        this.#resetPreview();
        this.inputTarget.value = '';
        this.#selectedFile = null;
        this.#refreshExistingPhotoSubState();
    }

    /**
     * Marque la photo déjà enregistrée pour suppression — purement local, aucun appel serveur.
     * La suppression réelle n'a lieu qu'à la soumission du formulaire (commitPendingChanges()),
     * sinon cliquer "Annuler" ensuite laisserait la suppression appliquée malgré tout.
     */
    onRemoveExisting() {
        this.#pendingDelete = true;
        this.#refreshExistingPhotoSubState();
    }

    /**
     * Applique les changements de photo en attente — appelé juste avant la soumission réelle du
     * formulaire d'édition (jamais si l'utilisateur clique "Annuler", qui ne fait que naviguer).
     * Un nouveau fichier sélectionné prend toujours priorité sur une suppression en attente
     * (remplacer == la photo existante disparaît de toute façon).
     */
    async commitPendingChanges() {
        if (this.#selectedFile) {
            return this.uploadIfSelected();
        }

        if (this.#pendingDelete && this.deleteUrlValue) {
            await sendDeleteRequest(this.deleteUrlValue, this.deleteCsrfTokenValue);
        }

        return null;
    }

    // ─── Lightbox ────────────────────────────────────────────────

    openLightbox() {
        this.lightboxImgTarget.src = this.previewTarget.src;
        this.#toggleLightbox(true);
    }

    closeLightbox() {
        this.#toggleLightbox(false);
    }

    // ─── Upload déclenché à la soumission ─────────────────────────

    async uploadIfSelected() {
        if (!this.#selectedFile) {
            return null;
        }

        return this.#upload(this.#selectedFile);
    }

    // ─── Privé ───────────────────────────────────────────────────

    #handleFile(file) {
        this.#clearError();

        const validationError = this.#validate(file);
        if (validationError) {
            this.#showError(validationError);
            return;
        }

        this.#selectedFile = file;
        this.#showPreview(file);

        // Upload toujours différé (édition comme création) jusqu'à uploadIfSelected()/
        // commitPendingChanges(), appelés à la soumission réelle du formulaire — jamais au choix
        // du fichier lui-même, sinon "Annuler" ne peut plus annuler ce changement (bug corrigé).
    }

    #validate(file) {
        if (!this.acceptedTypesValue.includes(file.type)) {
            return this.zoneTarget.dataset.errorType;
        }

        if (file.size > this.maxSizeValue) {
            return this.zoneTarget.dataset.errorSize;
        }

        return null;
    }

    #showPreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            // Upload rapide en échec (#upload) a pu entre-temps appeler #resetPreview()
            // et vider #selectedFile — ne pas réafficher un aperçu périmé dans ce cas.
            if (this.#selectedFile !== file) {
                return;
            }

            this.previewTarget.src = e.target.result;
            this.previewWrapperTarget.classList.remove('hidden');
            this.placeholderTarget.classList.add('hidden');
            this.filenameTarget.textContent = file.name;
        };
        reader.readAsDataURL(file);
    }

    /**
     * Bascule entre "photo déjà enregistrée" et "aucune photo, en ajouter une" à l'intérieur du
     * placeholder — sans effet en création, qui n'a pas ces deux targets (aucune photo ne peut
     * déjà exister pour une séance pas encore créée).
     */
    #refreshExistingPhotoSubState() {
        if (!this.hasExistingPhotoBlockTarget || !this.hasAddPromptBlockTarget) {
            return;
        }

        const showExisting = this.hasExistingPhotoValue && !this.#pendingDelete;
        this.existingPhotoBlockTarget.classList.toggle('hidden', !showExisting);
        this.addPromptBlockTarget.classList.toggle('hidden', showExisting);
    }

    #resetPreview() {
        this.previewTarget.src = '';
        this.previewWrapperTarget.classList.add('hidden');
        this.placeholderTarget.classList.remove('hidden');
        this.filenameTarget.textContent = '';
    }

    async #upload(file) {
        this.zoneTarget.classList.add('uploading');

        try {
            const response = await this.#fetchUpload(file);
            const data = await this.#parseResponse(response);

            this.dispatch('uploaded', { detail: { path: data.path, url: data.url } });

            return data;
        } catch (error) {
            this.#showError(error.message);
            this.#resetPreview();
            this.#selectedFile = null;
            return null;
        } finally {
            this.zoneTarget.classList.remove('uploading');
        }
    }

    async #fetchUpload(file) {
        const formData = new FormData();
        formData.append('photo', file);

        return fetch(this.uploadUrlValue, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    }

    async #parseResponse(response) {
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error ?? 'Upload failed');
        }

        return data;
    }

    #toggleLightbox(visible) {
        this.lightboxTarget.classList.toggle('hidden', !visible);
        this.lightboxTarget.classList.toggle('flex', visible);
    }

    #showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('hidden');
    }

    #clearError() {
        this.errorTarget.textContent = '';
        this.errorTarget.classList.add('hidden');
    }

    #preventDefaultDragBehavior() {
        ;['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
            document.addEventListener(eventName, (e) => e.preventDefault(), false);
        });
    }
}
