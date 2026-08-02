import { test, expect } from '@playwright/test';
import { FIXTURE_USERS, fillDatePicker, loginAs } from './helpers';

// Un seul utilisateur, deux tests dépendants (le second supprime la séance créée par le premier) :
// serial pour éviter qu'un run parallèle (playwright.config.ts a `fullyParallel: true`) ne fasse
// tourner les deux en même temps sur le même compte.
test.describe.configure({ mode: 'serial' });

test('user can create a workout with an exercise and a set', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);

    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    await fillDatePicker(page, '#workout_performedAt', '2026-01-15');
    // date_controller.js affiche une info (pas bloquante) si l'utilisateur a déjà une séance ce
    // jour-là (fixtures générées sur des dates aléatoires, cf. CLAUDE.md) — à fermer si présente,
    // sinon elle intercepte le clic suivant.
    const dateInfoModal = page.locator('.swal2-confirm');
    if (await dateInfoModal.isVisible().catch(() => false)) {
        await dateInfoModal.click();
    }

    // Sélection d'exercice via la LiveComponent ExerciseSelectorComponent (modale AJAX) : peu
    // importe lequel, on prend le premier résultat de la liste (référentiel public d'exercices).
    await page.getByText('Add an exercise').click();
    await page.locator('[data-live-action-param="selectExercise"]').first().click();

    // Une carte d'exercice avec un premier set vide est injectée par exercise-controller.js
    // (onExerciseSelected) — cf. templates/workout/create/_table.html.twig.
    await expect(page.locator('[data-exercise-index]')).toHaveCount(1);
    await page.locator('input[name*="[weight]"]').first().fill('80');
    await page.locator('input[name*="[reps]"]').first().fill('10');

    // "Submit" ouvre d'abord la modale de note (note_modal_manager.js) — la validation HTML5 du
    // formulaire principal (form.reportValidity()) s'y fait au clic sur son propre bouton, pas
    // avant l'ouverture de la modale.
    await page.getByRole('button', { name: 'Submit' }).click();
    await expect(page.locator('#note-modal')).toBeVisible();
    await page.locator('#note-modal-submit').click();

    // Soumission en fetch (pas de navigation classique) suivie d'un window.location.href manuel
    // vers la page de la séance créée.
    await expect(page).toHaveURL(/\/en\/workout\/[0-9a-f-]+/, { timeout: 15_000 });
});

test('user can delete a workout from the list', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);

    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');
    await fillDatePicker(page, '#workout_performedAt', '2026-02-10');
    const dateInfoModal = page.locator('.swal2-confirm');
    if (await dateInfoModal.isVisible().catch(() => false)) {
        await dateInfoModal.click();
    }
    await page.getByText('Add an exercise').click();
    await page.locator('[data-live-action-param="selectExercise"]').first().click();
    await page.locator('input[name*="[weight]"]').first().fill('50');
    await page.locator('input[name*="[reps]"]').first().fill('12');
    await page.getByRole('button', { name: 'Submit' }).click();
    await page.locator('#note-modal-submit').click();
    await expect(page).toHaveURL(/\/en\/workout\/([0-9a-f-]+)/, { timeout: 15_000 });

    const workoutId = new URL(page.url()).pathname.split('/').pop();

    // Filtre par date (?date=) pour retrouver la séance sans dépendre de la pagination — la liste
    // est triée par performedAt (date métier), pas par ordre de création (cf. CLAUDE.md, piège déjà
    // documenté côté fixtures : ne jamais supposer qu'une séance vient de tomber sur la 1re page).
    await page.goto('/en/my-workouts?date=2026-02-10');
    await page.waitForLoadState('networkidle');

    const deleteButton = page.locator(`button[data-workout-id="${workoutId}"]`);
    await deleteButton.scrollIntoViewIfNeeded();
    await deleteButton.click();

    // SweetAlert2 (utils/delete_confirmation.js) : confirmation avant l'appel DELETE réel. Succès
    // → toast puis `window.location.reload()` après 1200ms (cf. delete_modal_controller.js).
    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForURL('/en/my-workouts', { timeout: 5_000 }).catch(() => null);
    await page.waitForLoadState('networkidle');

    await expect(page.locator(`[data-workout-id="${workoutId}"]`)).toHaveCount(0);
});
