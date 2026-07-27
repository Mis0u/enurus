import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8001',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        // `-d variables_order=EGPCS` est indispensable : le serveur intégré de PHP ne peuple pas
        // $_SERVER depuis les variables d'environnement du process par défaut (seul getenv()
        // fonctionne) — sans ce flag, Symfony retombe sur APP_ENV=dev défini en dur dans .env,
        // et toute la suite tourne silencieusement contre le mauvais environnement/la mauvaise base.
        command: 'APP_ENV=test php -d variables_order=EGPCS -S 127.0.0.1:8001 -t public',
        url: 'http://127.0.0.1:8001',
        reuseExistingServer: !process.env.CI,
    },
});
