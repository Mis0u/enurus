import { test, expect } from '@playwright/test';
import { waitForCsrfControllerReady } from './helpers';

// Même mot de passe que tests/Functional/Security/RegistrationControllerTest.php : satisfait
// PasswordRuleEnum (longueur + regex).
const PASSWORD = 'pass_PASS?1234';

test('a new user can register and is sent to the check-email page', async ({ page }) => {
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

    // TODO #24 : plus de connexion auto après inscription — le compte reste inactif tant que
    // l'email n'est pas confirmé. RegistrationController redirige vers la page "vérifie ta boîte
    // mail" (RegistrationCheckEmailController), dans la locale de la requête.
    await expect(page).toHaveURL(/\/en\/register\/check-email/, { timeout: 15_000 });
    await expect(page.getByText(email)).toBeVisible();
});
