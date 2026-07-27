// Stub de test pour 'sweetalert2' : ce paquet n'est chargé qu'en importmap (AssetMapper), pas
// installé en npm, donc Vite ne peut pas le résoudre statiquement dans les tests Vitest. Cet alias
// (voir vitest.config.mjs) fournit un module réel que vi.mock() peut ensuite intercepter.
export default {
    fire() {
        throw new Error('Swal.fire() must be mocked via vi.mock() in tests.');
    },
};
