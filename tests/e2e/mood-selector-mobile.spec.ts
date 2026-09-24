import { test, expect, devices } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Régression mobile : les libellés des humeurs étaient masqués (`max-md:hidden`), ne laissant que
// l'icône et un `title` qui n'apparaît jamais au toucher — impossible de savoir à quoi correspond
// chaque icône. Vérifié en italien, où les libellés sont parmi les plus longs (« Infortunato »,
// « Grande record ») : chaque pastille doit rester lisible et tenir dans l'écran.
test.use({ ...devices['Pixel 7'] });

test('mood labels are visible on mobile', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/my-workouts');
    await page.waitForLoadState('networkidle');

    const editHref = await page.locator('a[href*="/edit"]').first().getAttribute('href');
    const workoutId = editHref?.match(/\/workout\/([0-9a-f-]+)\/edit/)?.[1];
    expect(workoutId).toBeTruthy();

    await page.goto(`/it/allenamento/${workoutId}/modifica`);
    await page.waitForLoadState('networkidle');

    const chips = page.locator('[data-workout--mood-selector-target="chip"]');
    await expect(chips).toHaveCount(5);

    const viewportWidth = page.viewportSize()?.width ?? 0;
    // Un libellé centré dans une cellule trop étroite déborde des deux côtés : on vérifie les
    // deux bords.
    const isInside = (inner: { x: number; width: number }, outer: { x: number; width: number }): boolean =>
        inner.x >= outer.x && inner.x + inner.width <= outer.x + outer.width;
    for (const chip of await chips.all()) {
        await chip.scrollIntoViewIfNeeded();
        const label = chip.locator('span');
        await expect(label).toBeVisible();

        const chipBox = await chip.boundingBox();
        const labelBox = await label.boundingBox();
        if (null === chipBox || null === labelBox) {
            throw new Error('Mood chip or label has no bounding box');
        }

        // Le libellé déborde de sa pastille si la mise en page mobile n'est pas appliquée
        // (5 colonnes trop étroites pour « Infortunato »).
        expect(isInside(labelBox, chipBox)).toBe(true);
        expect(isInside(chipBox, { x: 0, width: viewportWidth })).toBe(true);
    }

    await expect(chips.filter({ hasText: 'Infortunato' })).toBeVisible();
});
