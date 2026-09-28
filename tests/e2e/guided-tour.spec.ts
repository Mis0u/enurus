import { test, expect, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Tour guidé relancé depuis l'Aide (`?tour=1`, aucune écriture en base) : le premier affichage
// automatique marque le compte « vu » en base, il est couvert par DashboardGuidedTourTest (PHPUnit,
// transaction annulée) pour que ce spec reste rejouable. Sûr sur le compte partagé `workout11`.
const popover = (page: Page) => page.locator('.driver-popover');
// Pas de comptage des `.driver-active-element` : driver.js ne retire la classe de l'étape précédente
// qu'en fin d'animation, un clic rapide sur « Suivant » peut la laisser en place (sans effet visible,
// tout est nettoyé à la fermeture du tour).
async function expectHighlighted(page: Page, step: string): Promise<void> {
    await expect(page.locator(`.driver-active-element[data-tour-step="${step}"]`)).toBeVisible();
}

async function replayTourFromHelp(page: Page): Promise<void> {
    await page.goto('/fr/aide');
    await page.getByRole('link', { name: 'Revoir le tour guidé' }).click();
    await expect(popover(page)).toBeVisible();
}

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
});

test('an account that has already seen the tour does not get it again', async ({ page }) => {
    await page.waitForLoadState('networkidle');

    await expect(popover(page)).toHaveCount(0);
});

test('the tour walks through the main pages and ends on the workout form', async ({ page }) => {
    await replayTourFromHelp(page);
    await expect(popover(page)).toContainText('Bienvenue sur Enurus !');

    await page.getByRole('button', { name: 'Suivant' }).click();
    await expect(popover(page)).toContainText('Enregistrer une séance');
    await expectHighlighted(page, 'new_workout');

    await page.getByRole('button', { name: 'Suivant' }).click();
    await expectHighlighted(page, 'workouts');
    await page.getByRole('button', { name: 'Suivant' }).click();
    await expectHighlighted(page, 'library');
    await page.getByRole('button', { name: 'Suivant' }).click();
    await expect(popover(page)).toContainText('Ton tableau de bord');
    await page.getByRole('button', { name: 'Suivant' }).click();

    await expect(popover(page)).toContainText('Besoin d\'aide ?');
    await expect(popover(page)).toContainText('6 sur 6');
    await expectHighlighted(page, 'help');

    // Compte avec des séances : pas de « première séance ».
    await page.getByRole('button', { name: 'Enregistrer une séance' }).click();

    await expect(page).toHaveURL(/\/fr\/enregistre-seance/);
});

test('the tour can be closed at any time, with its translated close button or Escape', async ({ page }) => {
    await replayTourFromHelp(page);

    await page.getByRole('button', { name: 'Fermer le tour guidé' }).click();
    await expect(popover(page)).toHaveCount(0);

    await replayTourFromHelp(page);
    await page.keyboard.press('Escape');
    await expect(popover(page)).toHaveCount(0);
});
