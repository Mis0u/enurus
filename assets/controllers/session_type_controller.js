import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['free', 'routine'];

    toggle(event) {
        const clickedTarget = event.currentTarget.dataset.sessionTypeTarget;

        this.freeTarget.classList.remove('active');
        if (this.hasRoutineTarget) {
            this.routineTarget.classList.remove('active');
        }

        event.currentTarget.classList.add('active');

        if (this.hasRoutineTarget) {
            const routineSelector = document.getElementById('routine-selector');
            if (clickedTarget === 'routine') {
                routineSelector.classList.remove('hidden');
            } else {
                routineSelector.classList.add('hidden');
            }
        }
    }
}
