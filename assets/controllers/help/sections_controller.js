import { Controller } from '@hotwired/stimulus';

// Page Aide : un lien vers une section (sommaire, ou `/aide#library` depuis un autre écran) doit
// l'ouvrir — une ancre vers un <details> fermé ferait défiler jusqu'à un simple titre replié.
export default class extends Controller {
    static targets = ['section'];

    connect() {
        this.onHashChange = () => this.#openSection(window.location.hash.slice(1));
        window.addEventListener('hashchange', this.onHashChange);
        this.onHashChange();
    }

    disconnect() {
        window.removeEventListener('hashchange', this.onHashChange);
    }

    open(event) {
        this.#openSection(event.params.section);
    }

    #openSection(sectionId) {
        const section = this.sectionTargets.find((target) => target.id === sectionId);

        if (! section) {
            return;
        }

        section.open = true;
        section.scrollIntoView({ block: 'start' });
    }
}
