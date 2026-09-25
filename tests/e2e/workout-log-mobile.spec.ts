import { test, expect, devices, type Locator, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Régressions de l'audit mobile (enregistrer une séance) : écran réel d'un iPhone récent
// (390×844), où barre de navigation du bas + barre « Valider » fixes occupent le bas de l'écran.
// Aucun test ne soumet de séance : sûr sur le compte partagé `workout11`.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

// Taille minimale d'une zone tactile (recommandations Apple/WCAG : 44 px, tolérance à 40 px pour
// les actions secondaires d'une ligne de série, contraintes par la largeur de l'écran).
const MIN_TAP_SIZE = 40;

async function box(locator: Locator): Promise<{ x: number; y: number; width: number; height: number }> {
    const rect = await locator.boundingBox();
    if (null === rect) {
        throw new Error('Element has no bounding box');
    }

    return rect;
}

async function openLogWorkoutWithOneExercise(page: Page): Promise<void> {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    await page.getByText('Add an exercise').click();
    await page.locator('[data-live-action-param="selectExercise"]').first().click();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(1);
}

test('the workout date defaults to today and the log button is hidden from the bottom bar', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    const today = await page.evaluate(() => {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    });
    await expect(page.locator('#workout_performedAt')).toHaveValue(today);
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    await expect(page.locator('.bottom-nav-cta')).toHaveCount(0);
});

test('"Add an exercise" stays reachable above the submit bar', async ({ page }) => {
    await openLogWorkoutWithOneExercise(page);

    const addExercise = page.getByText('Add an exercise');
    await addExercise.scrollIntoViewIfNeeded();
    await page.locator('#exercises-list').evaluate(list => list.closest('.overflow-y-auto')?.scrollTo(0, 1e6));

    const addExerciseBox = await box(addExercise);
    const submitBox = await box(page.getByRole('button', { name: 'Submit' }));
    expect(addExerciseBox.y + addExerciseBox.height).toBeLessThanOrEqual(submitBox.y);
});

test('set actions are large enough to tap and do not overflow the screen', async ({ page }) => {
    await openLogWorkoutWithOneExercise(page);

    const repeat = page.getByRole('button', { name: 'Repeat this set' }).first();
    const remove = page.getByRole('button', { name: 'Delete this set' }).first();
    const repeatBox = await box(repeat);
    const removeBox = await box(remove);

    for (const actionBox of [repeatBox, removeBox]) {
        expect(actionBox.width).toBeGreaterThanOrEqual(MIN_TAP_SIZE);
        expect(actionBox.height).toBeGreaterThanOrEqual(MIN_TAP_SIZE);
    }
    expect(removeBox.x - (repeatBox.x + repeatBox.width)).toBeGreaterThanOrEqual(4);
    expect(removeBox.x + removeBox.width).toBeLessThanOrEqual(390);
});

test('submitting with an empty set reveals the missing field', async ({ page }) => {
    await openLogWorkoutWithOneExercise(page);
    const reps = page.locator('input[name*="[reps]"]').first();
    await page.locator('input[name*="[weight]"]').first().fill('80');
    // Vidé explicitement : la carte peut être pré-remplie avec la dernière performance.
    await reps.fill('');

    await page.getByRole('button', { name: 'Submit' }).click();

    await expect(reps).toBeFocused();
    await expect(reps).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('.swal2-toast')).toContainText('Fill in the fields in red');
    await expect(page.locator('#note-modal')).toBeHidden();

    // Le scroll est animé (`behavior: 'smooth'`) : on attend qu'il se stabilise au-dessus de la
    // barre « Valider ».
    const submitBox = await box(page.getByRole('button', { name: 'Submit' }));
    await expect.poll(async () => {
        const repsBox = await box(reps);
        return repsBox.y + repsBox.height <= submitBox.y && repsBox.y >= 0;
    }).toBe(true);
});

test('the edit action bar keeps both buttons on one line', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/my-workouts');
    await page.waitForLoadState('networkidle');
    const editHref = await page.locator('a[href*="/edit"]').first().getAttribute('href');
    if (null === editHref) {
        throw new Error('No workout to edit');
    }
    await page.goto(editHref);
    await page.waitForLoadState('networkidle');

    const cancelBox = await box(page.getByRole('link', { name: 'Cancel' }));
    const saveBox = await box(page.getByRole('button', { name: 'Save changes' }));
    expect(Math.abs(cancelBox.y - saveBox.y)).toBeLessThan(2);

    const addExercise = page.getByText('Add an exercise');
    await addExercise.scrollIntoViewIfNeeded();
    await page.locator('#workout-edit-form').evaluate(form => form.closest('.overflow-y-auto')?.scrollTo(0, 1e6));
    const addExerciseBox = await box(addExercise);
    expect(addExerciseBox.y + addExerciseBox.height).toBeLessThanOrEqual(saveBox.y);
});
