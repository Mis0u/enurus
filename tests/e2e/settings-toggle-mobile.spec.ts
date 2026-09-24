import { test, expect, devices } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Régression mobile : les interrupteurs utilisent un `<input class="peer sr-only">` (donc
// `position: absolute`). Sans `relative` sur le `<label>`, il est positionné par rapport au
// `<main class="overflow-hidden relative">` de _base_dashboard.html.twig, pas par rapport au
// wrapper qui scrolle : il ne suit pas le scroll du contenu. Au clic, le focus fait défiler ce
// `<main>` pour l'afficher → tout le contenu sort de l'écran (écran noir, seule la barre du bas
// fixe reste visible). Uniquement sur mobile, où la carte n'est visible qu'après avoir scrollé.
test.use({ ...devices['Pixel 7'] });

test('toggling a dashboard widget on mobile keeps the settings page on screen', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.dashboardSingle.email);
    await page.goto('/fr/reglages');
    await page.waitForLoadState('networkidle');

    // Carte repliée par défaut : on la déplie avant d'atteindre l'interrupteur.
    await page.locator('#dashboard-widgets summary').click();
    const toggle = page.locator('#dashboard-widgets label').first();
    await toggle.scrollIntoViewIfNeeded();

    // Deux clics : l'état du widget revient à celui des fixtures.
    for (let i = 0; 2 > i; i++) {
        await toggle.click();
        await expect(page.locator('.swal2-toast')).toBeVisible();

        expect(await page.locator('main').evaluate((main) => main.scrollTop)).toBe(0);
        await expect(toggle).toBeInViewport();
    }
});
