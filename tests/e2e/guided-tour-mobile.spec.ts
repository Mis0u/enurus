import { test, expect, devices, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Sur mobile, la sidebar est masquée : le tour doit viser la barre du bas (« + », onglets) et le
// bouton « Plus » pour l'Aide, et sa bulle doit tenir dans l'écran. Relance via l'Aide, aucune
// écriture (cf. guided-tour.spec.ts) : sûr sur le compte partagé `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const popover = (page: Page) => page.locator('.driver-popover');
// Pas de comptage des `.driver-active-element` : driver.js ne retire la classe de l'étape précédente
// qu'en fin d'animation, un clic rapide sur « Suivant » peut la laisser en place (sans effet visible,
// tout est nettoyé à la fermeture du tour).
async function expectHighlighted(page: Page, step: string): Promise<void> {
    await expect(page.locator(`.driver-active-element[data-tour-step="${step}"]`)).toBeVisible();
}
const bottomBar = (page: Page) => page.locator('nav[aria-label="Navigation principale"]');

async function goToStep(page: Page, stepNumber: number): Promise<void> {
    for (let step = 1; step < stepNumber; step++) {
        await page.getByRole('button', { name: 'Suivant' }).click();
    }
    await expect(popover(page)).toContainText(`${stepNumber} sur 6`);
}

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/fr/aide');
    await page.getByRole('link', { name: 'Revoir le tour guidé' }).click();
    await expect(popover(page)).toBeVisible();
});

test('the "log a workout" step points at the "+" of the bottom bar', async ({ page }) => {
    await goToStep(page, 2);

    await expectHighlighted(page, 'new_workout');
    await expect(bottomBar(page).locator('.driver-active-element')).toBeVisible();
});

const moreButton = (page: Page) => page.locator('button[data-mobile-more-menu-target="button"]');

test('the help step points at the "More" button, which holds the Help page on mobile', async ({ page }) => {
    await goToStep(page, 6);

    await expectHighlighted(page, 'help');
    await expect(moreButton(page)).toHaveClass(/driver-active-element/);
});

// driver.js écrase puis supprime les attributs ARIA de l'élément mis en avant.
test('the "More" button still controls its panel once the tour is closed', async ({ page }) => {
    await goToStep(page, 6);
    await page.keyboard.press('Escape');
    await expect(popover(page)).toHaveCount(0);

    await expect(moreButton(page)).toHaveAttribute('aria-controls', 'mobile-more-menu');
    await expect(moreButton(page)).toHaveAttribute('aria-haspopup', 'dialog');
    await moreButton(page).click();
    await expect(page.locator('dialog#mobile-more-menu')).toBeVisible();
});

test('the tour bubble and its buttons fit on the screen', async ({ page }) => {
    await goToStep(page, 6);

    for (const element of [popover(page), page.getByRole('button', { name: 'Enregistrer une séance' })]) {
        const box = await element.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.x).toBeGreaterThanOrEqual(0);
        expect(box!.x + box!.width).toBeLessThanOrEqual(390);
    }
});
