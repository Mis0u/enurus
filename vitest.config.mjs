import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            // 'html-to-image' et 'sweetalert2' ne sont chargés qu'en importmap (AssetMapper), pas
            // installés en npm : Vite ne peut pas les résoudre statiquement sans ces alias vers
            // des stubs de test (interceptables ensuite via vi.mock()).
            'html-to-image': fileURLToPath(new URL('./tests/js/stubs/html-to-image.js', import.meta.url)),
            sweetalert2: fileURLToPath(new URL('./tests/js/stubs/sweetalert2.js', import.meta.url)),
            sortablejs: fileURLToPath(new URL('./tests/js/stubs/sortablejs.js', import.meta.url)),
            'flatpickr/dist/flatpickr.min.css': fileURLToPath(new URL('./tests/js/stubs/flatpickr-css.js', import.meta.url)),
            'flatpickr/dist/l10n/fr.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            'flatpickr/dist/l10n/es.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            'flatpickr/dist/l10n/it.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            'flatpickr/dist/l10n/de.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            'flatpickr/dist/l10n/nl.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            'flatpickr/dist/l10n/pl.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            'flatpickr/dist/l10n/pt.js': fileURLToPath(new URL('./tests/js/stubs/flatpickr-l10n.js', import.meta.url)),
            flatpickr: fileURLToPath(new URL('./tests/js/stubs/flatpickr.js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
    },
});
