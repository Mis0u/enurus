import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['zone', 'input', 'preview', 'previewWrapper', 'placeholder', 'error', 'filename', 'lightbox', 'lightboxImg'];

    static values = {
        uploadUrl: { type: String, default: '' },
        maxSize: { type: Number, default: 5242880 }, // 5 Mo
        acceptedTypes: { type: Array, default: ['image/jpeg', 'image/png', 'image/webp'] },
    };

    #selectedFile = null;

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

        // Si l'URL est déjà connue (page d'édition), upload immédiat
        // Sinon (modale création), l'upload sera déclenché par uploadIfSelected()
        if (this.uploadUrlValue) {
            this.#upload(file);
        }
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
            this.previewTarget.src = e.target.result;
            this.previewWrapperTarget.classList.remove('hidden');
            this.placeholderTarget.classList.add('hidden');
            this.filenameTarget.textContent = file.name;
        };
        reader.readAsDataURL(file);
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
