import { test, expect, devices, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Bibliothèque sur mobile : une ligne compacte par exercice (nom + muscles, actions à droite),
// dépliée par un appui pour montrer la description. Aucune écriture : sûr sur `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const COMPACT_ROW_MAX_HEIGHT = 72;
const visibleRows = (page: Page) => page.locator('[data-exercise--list-target="card"]:visible');

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/fr/bibliotheque');
    await page.waitForLoadState('networkidle');
});

test('exercises are listed as compact rows', async ({ page }) => {
    const heights = await visibleRows(page).evaluateAll((rows) => rows.map((row) => row.getBoundingClientRect().height));

    expect(heights.length).toBeGreaterThan(0);
    expect(Math.max(...heights)).toBeLessThanOrEqual(COMPACT_ROW_MAX_HEIGHT);
});

test('a tap on a row unfolds its description, a second tap folds it back', async ({ page }) => {
    const row = visibleRows(page).first();
    const toggle = row.locator('[data-exercise--card-target="toggle"]');
    const details = row.locator('.exercise-list-card__details');
    await expect(details).toBeHidden();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(details).toBeVisible();

    await toggle.click();
    await expect(details).toBeHidden();
});

test('the muscle filter keeps the full width while a type filter is active', async ({ page }) => {
    await page.locator('#filterBase').click();
    await expect(page.locator('[data-exercise--list-target="resetBtn"]')).toBeVisible();

    await page.getByText('Filtrer par muscle').click();
    const chips = page.locator('[data-exercise--list-target="muscleFilterChip"]').first().locator('xpath=..');
    const chipsBox = await chips.boundingBox();

    expect(chipsBox?.width ?? 0).toBeGreaterThan(page.viewportSize()!.width * 0.8);
    await expect(page.locator('[data-exercise--list-target="counter"]:visible')).toHaveCount(1);
});

test('the counter stays in the page language after filtering', async ({ page }) => {
    await page.goto('/en/library');
    await page.waitForLoadState('networkidle');
    const counter = page.locator('[data-exercise--list-target="counterText"]:visible');
    await expect(counter).toHaveText(/^\d+ exercises$/);

    await page.locator('[data-exercise--list-target="searchInput"]').fill('curl');

    await expect(counter).toHaveText(/^\d+ exercises?$/);
});
