import { test, expect } from '@playwright/test';
import { waitForCsrfControllerReady } from './helpers';

// Même mot de passe que tests/Functional/Security/RegistrationControllerTest.php : satisfait
// PasswordRuleEnum (longueur + regex) et n'est pas flaggé par NotCompromisedPassword.
const PASSWORD = 'pass_PASS?1234';

test('a new user can register and lands on their dashboard', async ({ page }) => {
    // Email unique par run pour éviter une collision "déjà inscrit" entre exécutions successives.
    const email = `e2e-registration-${Date.now()}@test.com`;

    await page.goto('/en/register');
    await waitForCsrfControllerReady(page);

    // Pas d'id sur ces radios (cf. templates/registration/theme/gender_theme.html.twig, form theme
    // custom sans id explicite) — ciblage par name+value.
    await page.locator('input[name="registration_form[gender]"][value="male"]').check();
    await page.locator('#registration_form_nickname').fill('e2e-registrant');
    await page.locator('#registration_form_email').fill(email);
    await page.locator('#registration_form_plainPassword').fill(PASSWORD);
    // Honeypot (#registration_form_website) et formStarted (timestamp serveur) : ne jamais y
    // toucher, cf. BotDetectionService — un champ honeypot rempli ou un submit < 5s après le
    // rendu de la page fait échouer l'inscription silencieusement (redirect vers le login avec un
    // flash "success" trompeur, cf. RegistrationController::handleSecurityFailure).
    await page.waitForTimeout(5_500);

    await page.getByRole('button', { name: 'Create my account' }).click();

    // RegistrationController connecte automatiquement l'utilisateur après inscription et redirige
    // vers son tableau de bord, dans la locale de la requête ('en' ici, contrairement au login où
    // LoginSuccessListener utilise la locale déjà enregistrée sur le compte — cf. login.spec.ts).
    await expect(page).toHaveURL(/\/en\/dashboard/, { timeout: 15_000 });
});
