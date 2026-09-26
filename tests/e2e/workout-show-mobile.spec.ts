import { test, expect, devices } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Détail d'une séance sur mobile : résumé sur une ligne, silhouettes côte à côte, muscles des
// exercices en texte. Aucune écriture : sûr sur `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/fr/mes-seances');
    await page.waitForLoadState('networkidle');
    await page.goto((await page.locator('a.workout-row-link').first().getAttribute('href'))!);
    await page.waitForLoadState('networkidle');
});

test('the front and back silhouettes sit side by side', async ({ page }) => {
    const silhouettes = page.locator('.svg-body-container > div');
    const [front, back] = [await silhouettes.nth(0).boundingBox(), await silhouettes.nth(1).boundingBox()];

    expect(Math.abs((front?.y ?? 0) - (back?.y ?? 1000))).toBeLessThan(5);
});

test('exercises, sets and reps fit on one line, without repeating tonnage and duration', async ({ page }) => {
    const visibleTiles = page.locator('.show-header-card .grid > div:visible');

    await expect(visibleTiles).toHaveCount(3);
    const tops = await visibleTiles.evaluateAll((tiles) => tiles.map((tile) => Math.round(tile.getBoundingClientRect().top)));
    expect(new Set(tops).size).toBe(1);
});

test('each exercise lists its muscles as text', async ({ page }) => {
    await expect(page.locator('.show-exercise-muscles__primary:visible').first()).not.toBeEmpty();
    await expect(page.locator('.show-muscle-tag-primary:visible')).toHaveCount(0);
});
