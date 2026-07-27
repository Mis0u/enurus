// Stub de test pour 'sortablejs' : ce paquet n'est chargé qu'en importmap (AssetMapper), pas
// installé en npm, donc Vite ne peut pas le résoudre statiquement dans les tests Vitest. Cet alias
// (voir vitest.config.mjs) fournit un module réel que vi.mock() peut ensuite intercepter — sinon,
// par défaut, une fausse instance sans comportement de drag réel (le drag lui-même n'est pas
// testable en jsdom ; seul le callback onEnd déclenché manuellement dans les tests l'est).
export default class Sortable {
    constructor(el, options = {}) {
        this.el = el;
        this.options = options;
    }

    destroy() {}
}
