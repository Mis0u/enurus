import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        primary: Array,
        secondary: Array,
    };

    connect() {
        console.log('primary:', this.primaryValue);
        console.log('secondary:', this.secondaryValue);
        this.applyColors();
    }

    applyColors() {
        const container = this.element;

        // Reset tous les groupes bodymap
        container.querySelectorAll('g.bodymap').forEach(g => {
            g.style.fill = '#1e293b';
            g.style.stroke = 'rgba(255,255,255,0.1)';
            g.style.color = '#1e293b';
        });

        // Primaires
        this.primaryValue.forEach(id => {
            container.querySelectorAll(`#${id}`).forEach(el => {
                el.style.fill = 'rgba(244,63,94,0.55)';
                el.style.stroke = '#f43f5e';
                el.style.color = 'rgba(244,63,94,0.55)';
            });
        });

        // Secondaires
        this.secondaryValue.forEach(id => {
            container.querySelectorAll(`#${id}`).forEach(el => {
                el.style.fill = 'rgba(249,115,22,0.45)';
                el.style.stroke = '#f97316';
                el.style.color = 'rgba(249,115,22,0.45)';
            });
        });
    }

    disconnect() {
        if (this._injectedStyle) {
            this._injectedStyle.remove();
        }
    }
}
