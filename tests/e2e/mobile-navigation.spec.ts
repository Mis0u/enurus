import { test, expect, devices, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Navigation mobile : une seule barre du bas, les pages restantes dans le panneau « Plus ».
// Aucune écriture : sûr sur le compte partagé `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const moreButton = (page: Page) => page.locator('button[aria-controls="mobile-more-menu"]');
const panel = (page: Page) => page.locator('dialog#mobile-more-menu');

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.waitForLoadState('networkidle');
});

test('the "More" panel leads to a page, and stays highlighted there', async ({ page }) => {
    await expect(page.getByRole('button', { name: 'Ouvrir le menu' })).toHaveCount(0);

    await moreButton(page).click();
    await expect(panel(page)).toBeVisible();
    await expect(moreButton(page)).toHaveAttribute('aria-expanded', 'true');

    await panel(page).getByRole('link', { name: 'Mes routines' }).click();

    await expect(page).toHaveURL(/\/fr\/mes-routines/);
    await expect(panel(page)).toBeHidden();
    await expect(moreButton(page)).toHaveAttribute('data-active', 'true');
});

test('the "More" panel closes with Escape and with a tap beside it', async ({ page }) => {
    await moreButton(page).click();
    await page.keyboard.press('Escape');
    await expect(panel(page)).toBeHidden();
    await expect(moreButton(page)).toHaveAttribute('aria-expanded', 'false');

    await moreButton(page).click();
    await expect(panel(page)).toBeVisible();
    await page.mouse.click(195, 40);
    await expect(panel(page)).toBeHidden();
});

test('the library is one tap away', async ({ page }) => {
    await page.locator('nav[aria-label="Navigation principale"] > a[href="/fr/bibliotheque"]').click();

    await expect(page).toHaveURL(/\/fr\/bibliotheque/);
});
