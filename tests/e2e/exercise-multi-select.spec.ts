import { test, expect, devices } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Sélection multiple du sélecteur d'exercices, sur mobile : cocher plusieurs exercices puis
// « Add (N) » ajoute une carte par exercice, dans l'ordre où ils ont été cochés. Aucune séance
// enregistrée : sûr sur le compte partagé `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

test('checked exercises are added at once, in the order they were checked', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    await page.locator('[data-live-action-param="open"]').click();
    const checkboxes = page.locator('input[data-model="norender|selectedIds[]"]');
    await expect(checkboxes.first()).toBeAttached();
    const listedIds = await checkboxes.evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
    // Ordre de coche volontairement inverse de celui de la liste.
    const checkedIds = [...new Set(listedIds)].slice(0, 3).reverse();

    const addButton = page.locator('[data-exercise-selector-target="addButton"]');
    await expect(addButton).toBeDisabled();
    for (const id of checkedIds) {
        await page.locator(`label:has(input[value="${id}"])`).first().click();
    }
    await expect(addButton).toHaveText('Add (3)');

    await addButton.click();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(3);

    const addedIds = await page.locator('[data-exercise-index] input[name$="[exercise]"]')
        .evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
    expect(addedIds).toEqual(checkedIds);
    await expect(page.locator('[data-live-action-param="close"]')).toHaveCount(0);
});

test('the selection survives a search', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    await page.locator('[data-live-action-param="open"]').click();
    const firstCheckbox = page.locator('input[data-model="norender|selectedIds[]"]').first();
    const firstId = await firstCheckbox.getAttribute('value');
    await page.locator(`label:has(input[value="${firstId}"])`).first().click();

    const search = page.locator('[data-model="on(input)|search"]');
    await search.fill('zzz-no-match');
    await expect(page.locator('input[data-model="norender|selectedIds[]"]')).toHaveCount(0);
    await search.fill('');

    await expect(page.locator(`input[value="${firstId}"]`).first()).toBeChecked();
    await expect(page.locator('[data-exercise-selector-target="addButton"]')).toHaveText('Add (1)');
});
