import { Controller } from '@hotwired/stimulus';

const BYTES_PER_UNIT = 1024;

export default class extends Controller {
    static targets = ['input', 'error', 'errorText', 'preview', 'thumb', 'filename', 'filesize'];
    static values = {
        maxSize: Number,
        allowedTypes: Array,
        tooLargeError: String,
        invalidTypeError: String,
    };

    validate() {
        const file = this.inputTarget.files[0];

        if (!file) {
            this.#hideError();
            this.#hidePreview();
            return;
        }

        const violation = this.#violation(file);

        if (violation) {
            this.#showError(violation);
            this.#hidePreview();
            this.inputTarget.value = '';
            return;
        }

        this.#hideError();
        this.#showPreview(file);
    }

    remove() {
        this.inputTarget.value = '';
        this.#hideError();
        this.#hidePreview();
    }

    #violation(file) {
        if (!this.allowedTypesValue.includes(file.type)) {
            return this.invalidTypeErrorValue;
        }

        if (file.size > this.maxSizeValue) {
            return this.tooLargeErrorValue;
        }

        return null;
    }

    #showPreview(file) {
        this.filenameTarget.textContent = file.name;
        this.filesizeTarget.textContent = this.#formatFileSize(file.size);

        const reader = new FileReader();
        reader.onload = (event) => {
            this.thumbTarget.innerHTML = `<img src="${event.target.result}" alt="" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(file);

        this.previewTarget.classList.remove('hidden');
        this.previewTarget.classList.add('flex');
    }

    #hidePreview() {
        this.previewTarget.classList.add('hidden');
        this.previewTarget.classList.remove('flex');
        this.thumbTarget.innerHTML = '';
    }

    #showError(message) {
        this.errorTextTarget.textContent = message;
        this.errorTarget.classList.remove('hidden');
        this.errorTarget.classList.add('flex');
    }

    #hideError() {
        this.errorTarget.classList.add('hidden');
        this.errorTarget.classList.remove('flex');
    }

    #formatFileSize(bytes) {
        if (BYTES_PER_UNIT > bytes) {
            return `${bytes} o`;
        }

        if (BYTES_PER_UNIT * BYTES_PER_UNIT > bytes) {
            return `${Math.round(bytes / BYTES_PER_UNIT)} Ko`;
        }

        return `${(bytes / (BYTES_PER_UNIT * BYTES_PER_UNIT)).toFixed(1)} Mo`;
    }
}
