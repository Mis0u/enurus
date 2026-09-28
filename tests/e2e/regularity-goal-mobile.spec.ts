import { test, expect, devices, type Page } from '@playwright/test';
import { loginAs } from './helpers';

// Objectif de régularité sur mobile : création depuis la modale de l'onglet Calendrier (date du
// jour pré-remplie), affichage dans la carte, puis abandon — le test remet le compte dans son état
// initial. `user-fixture-9` (UserFixtures::loadIndexedUsers()) n'est utilisé par aucun autre test.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const USER = 'user-fixture-9@test.com';

const goalCard = (page: Page) => page.locator('section[aria-labelledby="regularity-goal-title"]');
const goalModal = (page: Page) => page.getByRole('dialog', { name: 'Nouvel objectif de régularité' });

test.beforeEach(async ({ page }) => {
    await loginAs(page, USER);
    await page.goto('/fr/mes-seances?view=calendar');
    await page.waitForLoadState('networkidle');
});

test('a regularity goal can be created from the calendar tab, then abandoned', async ({ page }) => {
    await goalCard(page).getByRole('button', { name: 'Nouvel objectif' }).click();
    await expect(goalModal(page)).toBeVisible();

    const modalBox = await goalModal(page).locator('> div').boundingBox();
    expect(modalBox!.x).toBeGreaterThanOrEqual(0);
    expect(modalBox!.x + modalBox!.width).toBeLessThanOrEqual(390);

    // Sur mobile, flatpickr passe la main au sélecteur natif : il ne doit proposer ni date passée
    // ni borne de fin (un objectif peut démarrer demain).
    const nativeDateInput = goalModal(page).locator('input.flatpickr-mobile');
    await expect(nativeDateInput).toHaveAttribute('min', await page.evaluate(() => new Date().toLocaleDateString('sv')));
    await expect(nativeDateInput).not.toHaveAttribute('max', /.+/);

    await goalModal(page).getByLabel('Séances').fill('2');
    await goalModal(page).getByLabel('Par').selectOption('week');
    await goalModal(page).getByLabel('Pendant').selectOption('2');
    await goalModal(page).getByRole('button', { name: "Créer l'objectif" }).click();

    await expect(goalCard(page)).toContainText('2 séances par semaine pendant 2 semaines');
    await expect(goalCard(page).locator('li[title]')).toHaveCount(2);
    await expect(goalCard(page).getByRole('button', { name: 'Nouvel objectif' })).toHaveCount(0);

    await goalCard(page).getByRole('button', { name: 'Abandonner' }).click();
    await page.locator('.swal2-confirm').click();

    await expect(goalCard(page).getByRole('button', { name: 'Nouvel objectif' })).toBeVisible();
    await expect(goalCard(page)).toContainText('Aucun objectif en cours.');
});
