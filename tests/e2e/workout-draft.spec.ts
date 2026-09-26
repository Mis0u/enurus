import { test, expect, devices, type Page } from '@playwright/test';
import { addExercises, FIXTURE_USERS, loginAs } from './helpers';

// Brouillon auto-enregistré de la séance en cours (workout--draft), sur mobile : la saisie
// survit à un rechargement et à un détour par la création d'un exercice manquant. Le brouillon
// vit dans le localStorage du contexte de navigateur, propre à chaque test.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const banner = (page: Page) => page.locator('[data-workout--draft-target="banner"]');
// 1er et 2e champ de la 1re série d'une carte (poids puis reps, durée ou distance selon l'exercice).
const setInput = (page: Page, card: number, field: number) =>
    page.locator(`[data-exercise-index="${card}"] .js-sets-tbody > tr:first-child input[type="number"]`).nth(field);

async function typeFirstSets(page: Page): Promise<void> {
    await setInput(page, 0, 0).fill('42.5');
    await setInput(page, 0, 1).fill('8');
    await setInput(page, 1, 0).fill('30');
    await setInput(page, 1, 1).fill('12');
}

async function exerciseIds(page: Page): Promise<string[]> {
    return page.locator('[data-exercise-index] input[name$="[exercise]"]')
        .evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
}

test('the workout in progress is restored after a reload', async ({ page }) => {
    // Rien n'est enregistré : sûr sur le compte partagé `workout11`.
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    const ids = await addExercises(page, 2);
    await typeFirstSets(page);
    await page.reload();

    await expect(banner(page)).toBeVisible();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(2);
    expect(await exerciseIds(page)).toEqual(ids);
    await expect(setInput(page, 0, 0)).toHaveValue('42.5');
    await expect(setInput(page, 0, 1)).toHaveValue('8');
    await expect(setInput(page, 1, 1)).toHaveValue('12');

    await page.getByRole('button', { name: 'Start over' }).click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('[data-exercise-index]')).toHaveCount(0);
    await expect(banner(page)).toBeHidden();
});

test('leaving and coming back through the menu neither duplicates the cards nor drops the carry-over notice', async ({ page }) => {
    // Rien n'est enregistré : sûr sur le compte partagé `workout11`, dont les exercices
    // habituels ont un historique (cartes pré-remplies avec la dernière performance).
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    const ids = await addExercises(page, 2);
    const noticesBefore = await page.locator('.js-prefilled-from').count();
    expect(noticesBefore).toBeGreaterThan(0);

    // Navigation Turbo (liens), pas un rechargement complet.
    await page.locator('a[href="/en/dashboard"]').filter({ visible: true }).first().click();
    await expect(page).toHaveURL(/\/en\/dashboard/);
    await page.locator('a[href="/en/log-workout"]').filter({ visible: true }).first().click();
    await expect(page).toHaveURL(/\/en\/log-workout/);
    await page.waitForLoadState('networkidle');

    await expect(banner(page)).toBeVisible();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(2);
    expect(await exerciseIds(page)).toEqual(ids);
    await expect(page.locator('.js-prefilled-from')).toHaveCount(noticesBefore);
});

test('an exercise created on the way is added to the restored workout', async ({ page }) => {
    // Crée un exercice perso : compte dédié `dashboardSingle`, jamais le compte partagé.
    await loginAs(page, FIXTURE_USERS.dashboardSingle.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    const ids = await addExercises(page, 2);
    await typeFirstSets(page);

    await page.locator('[data-live-action-param="open"]').click();
    await page.locator('[data-model="on(input)|search"]').fill('zzz-missing-exercise');
    await page.getByRole('link', { name: /Create it/ }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Your workout is saved');
    await page.locator('.swal2-confirm').click();

    await expect(page).toHaveURL(/\/en\/library\/exercise\/create\?returnTo=workout/);
    await page.waitForLoadState('networkidle');
    await page.locator('#exercise_name').fill(`Draft e2e ${Date.now()}`);
    await page.locator('[data-exercise--create-target="pill"]').first().click();
    await page.locator('form button[type="submit"]').click();

    await expect(page).toHaveURL(/\/en\/log-workout$/, { timeout: 15_000 });
    await expect(banner(page)).toBeVisible();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(3);
    const restoredIds = await exerciseIds(page);
    expect(restoredIds.slice(0, 2)).toEqual(ids);
    await expect(setInput(page, 0, 0)).toHaveValue('42.5');
    await expect(setInput(page, 1, 1)).toHaveValue('12');
});
