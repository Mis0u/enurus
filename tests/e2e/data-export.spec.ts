import { test, expect } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Le fichier d'export est préparé en entier côté serveur avant l'envoi : le lien passe par
// `fetch` (contrôleur settings--data-export) pour afficher un spinner pendant la préparation, puis
// enregistre le fichier sous le nom donné par le serveur.
for (const format of ['csv', 'json']) {
    test(`the ${format} export downloads the file and frees the link afterwards`, async ({ page }) => {
        await loginAs(page, FIXTURE_USERS.workout11.email);
        await page.goto('/fr/reglages');
        await page.waitForLoadState('networkidle');

        const exportLink = page.locator(`a[href="/fr/reglages/export/${format}"]`);
        const downloadPromise = page.waitForEvent('download');
        await exportLink.click();
        const download = await downloadPromise;

        expect(download.suggestedFilename()).toMatch(new RegExp(`^enurus-workouts-\\d{4}-\\d{2}-\\d{2}\\.${format}$`));
        await expect(exportLink).not.toHaveAttribute('aria-busy');
        await expect(exportLink.locator('[data-submit-spinner]')).toHaveCount(0);
    });
}
