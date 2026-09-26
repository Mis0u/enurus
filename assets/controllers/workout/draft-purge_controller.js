// Efface les brouillons de séance (cf. draft_storage.js) gardés sur l'appareil — posé sur la page
// de connexion, où arrive toute déconnexion.

import { Controller } from '@hotwired/stimulus';
import { purgeAllDrafts } from './draft_storage.js';

export default class extends Controller {
    connect() {
        purgeAllDrafts();
    }
}
