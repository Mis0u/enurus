import { test, expect, devices, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// « Mes séances » sur mobile : une carte compacte par séance, qui mène au détail par un vrai lien
// (étiré sur la carte), sans capturer les boutons d'action. Aucune écriture : sûr sur `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const COMPACT_ROW_MAX_HEIGHT = 130;
const rows = (page: Page) => page.locator('.workout-row');

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/fr/mes-seances');
    await page.waitForLoadState('networkidle');
});

test('workouts are listed as compact cards', async ({ page }) => {
    const heights = await rows(page).evaluateAll((cards) => cards.map((card) => card.getBoundingClientRect().height));

    expect(heights.length).toBeGreaterThan(0);
    expect(Math.max(...heights)).toBeLessThanOrEqual(COMPACT_ROW_MAX_HEIGHT);
});

test('a tap on the card opens the workout', async ({ page }) => {
    const showUrl = await rows(page).first().locator('a.workout-row-link').getAttribute('href');

    // Sur la carte, à côté du titre : c'est le lien étiré qui reçoit le toucher.
    await rows(page).first().click({ position: { x: 24, y: 16 } });

    await expect(page).toHaveURL(showUrl!);
});

test('a tap on the edit button opens the edit page, not the workout', async ({ page }) => {
    await rows(page).first().locator('a[href$="/modifier"]').click();

    await expect(page).toHaveURL(/\/fr\/seance\/[0-9a-f-]+\/modifier$/);
});
