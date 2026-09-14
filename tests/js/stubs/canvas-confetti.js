// Stub de test pour 'canvas-confetti' : ce paquet n'est chargé qu'en importmap (AssetMapper), pas
// installé en npm, donc Vite ne peut pas le résoudre statiquement dans les tests Vitest. Cet alias
// (voir vitest.config.mjs) fournit un module réel que vi.mock() peut ensuite intercepter.
export default function confetti() {
    throw new Error('confetti() must be mocked via vi.mock() in tests.');
}
