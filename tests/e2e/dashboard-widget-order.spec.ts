import { test, expect, devices, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Réorganisation des widgets dans Réglages › Widgets du dashboard, au doigt (flèches ↑ ↓ : la
// poignée de glisser-déposer est réservée à la souris), puis vérification sur le dashboard.
// Écrit l'ordre en base : compte dédié `widgetOrder`, recharger les fixtures de test après un run.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const widgetRows = (page: Page) => page.locator('[data-settings--dashboard-widgets-target="row"]');
const dashboardWidgets = (page: Page) => page.locator('[data-dashboard-widget]');

async function openWidgetSettings(page: Page): Promise<void> {
    await page.goto('/fr/reglages');
    await page.locator('#dashboard-widgets summary').click();
    await expect(widgetRows(page).first()).toBeVisible();
}

async function moveUp(page: Page, widgetLabel: string): Promise<void> {
    const saved = page.waitForResponse((response) => response.url().includes('/reglages/widgets/ordre') && response.ok());
    await page.getByRole('button', { name: `Monter « ${widgetLabel} »` }).click();
    await saved;
}

test.beforeEach(async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.widgetOrder.email);
});

test('widgets reordered in the settings keep that order on the dashboard', async ({ page }) => {
    await openWidgetSettings(page);
    await expect(widgetRows(page).first()).toHaveAttribute('data-widget', 'session');

    await moveUp(page, 'Tonnage');

    await expect(widgetRows(page).first()).toHaveAttribute('data-widget', 'tonnage');
    await page.goto('/fr/tableau-de-bord');
    await expect(dashboardWidgets(page).first()).toHaveAttribute('data-dashboard-widget', 'tonnage');
    await expect(dashboardWidgets(page).nth(1)).toHaveAttribute('data-dashboard-widget', 'session');
});

test('the arrows are big enough to tap and the drag handle is hidden on mobile', async ({ page }) => {
    await openWidgetSettings(page);

    const upArrow = widgetRows(page).last().locator('[data-settings--dashboard-widgets-target="moveUpButton"]');
    const box = await upArrow.boundingBox();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);
    await expect(page.locator('#dashboard-widgets .drag-handle').first()).toBeHidden();
    // Premier widget de la liste, quel qu'il soit : l'autre test de ce fichier, lancé en parallèle
    // sur le même compte, change l'ordre.
    await expect(widgetRows(page).first().locator('[data-settings--dashboard-widgets-target="moveUpButton"]')).toBeDisabled();
});
