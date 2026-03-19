import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    showModal() {
        document.getElementById('searchModal').classList.add('show');
        document.getElementById('exerciseSearch').focus();
    }

    closeModal() {
        document.getElementById('searchModal').classList.remove('show');
    }

    closeOnOverlay(event) {
        if (event.target === document.getElementById('searchModal')) {
            this.closeModal();
        }
    }
}
