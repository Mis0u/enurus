import { test, expect, devices, type Locator } from '@playwright/test';
import { loginAs } from './helpers';

// Tableau de bord compact sur mobile : chiffres de la séance sur une rangée, 5 barres musculaires,
// onglets sur une ligne, écarts de régularité abrégés. Lecture seule, `user-fixture-51-workout`
// (tous les widgets débloqués) — pas `fixture-26`, dont la visite casse `BadgeFlowTest`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const MAX_COMPACT_MUSCLE_BARS = 5;
const SINGLE_LINE_TAB_HEIGHT = 44;

async function distinctTops(elements: Locator): Promise<number> {
    const tops = await elements.evaluateAll((nodes) => nodes.map((node) => Math.round(node.getBoundingClientRect().top)));

    return new Set(tops).size;
}

test.beforeEach(async ({ page }) => {
    await loginAs(page, 'user-fixture-51-workout@test.com');
    await page.waitForLoadState('networkidle');
});

test('session figures sit on a single row', async ({ page }) => {
    const figures = page.locator('[data-dashboard--session-target="sessions"], [data-dashboard--session-target="exercises"], [data-dashboard--session-target="sets"], [data-dashboard--session-target="reps"]');

    await expect(figures).toHaveCount(4);
    expect(await distinctTops(figures)).toBe(1);
});

test('the "last day" tabs keep a short label on one line', async ({ page }) => {
    const lastDayTabs = page.locator('button[data-filter="last"], button[data-filter="session"]');

    for (const tab of await lastDayTabs.all()) {
        expect((await tab.innerText()).trim()).toBe('Dernier jour');
        expect((await tab.boundingBox())?.height).toBeLessThanOrEqual(SINGLE_LINE_TAB_HEIGHT);
    }
});

test('worked muscles show at most 5 bars, the rest summed up', async ({ page }) => {
    const activePanel = page.locator('[data-dashboard--muscles-target="barsPanel"]:visible');
    const bars = activePanel.locator(':scope > div');

    expect(await activePanel.locator(':scope > div:visible').count()).toBeLessThanOrEqual(MAX_COMPACT_MUSCLE_BARS);
    // Séances fixtures tirées au hasard : le résumé n'existe que si des barres ont été masquées.
    if (await bars.count() > MAX_COMPACT_MUSCLE_BARS) {
        await expect(activePanel.locator('p:visible')).toHaveText(/autres? muscles? travaillés?/);
    }
});

test('regularity deltas are abbreviated', async ({ page }) => {
    // La version longue reste dans le DOM en `sr-only` (1 px, « visible » pour Playwright) : seule
    // la présence de la version courte est vérifiée.
    await expect(page.locator('div[aria-hidden="true"]', { hasText: /\d vs préc\./ }).first()).toBeVisible();
});
