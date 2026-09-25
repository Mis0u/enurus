import { test, expect, devices, type Page } from '@playwright/test';
import { addExercises, loginAs } from './helpers';

// Garde-fou de l'audit mobile : aucun élément interactif ne doit être trop petit pour le doigt sur
// les pages principales. Cible 44 px (recommandation Apple/WCAG AAA), plancher 40 px toléré pour
// les listes denses (puces de filtre muscle, icônes d'action d'une ligne, boutons d'une série) —
// ce test vérifie le plancher commun à tous.
//
// Exemptions (mêmes règles que l'audit) :
// - `<label>` d'un champ texte : c'est le champ lui-même qu'on touche ;
// - lien au fil d'un texte (`display: inline` dans un `<p>`) : exception WCAG 2.5.8 ;
// - éléments visuellement masqués (`sr-only`, input `peer` d'un interrupteur dont le `<label>`
//   est la vraie zone tactile).
//
// `user-fixture-51-workout` : 51 séances (pagination sur plusieurs pages), pages non vides. Pas
// `user-fixture-26-workout` : sa visite synchronise ses badges en base de test et casse
// `BadgeFlowTest`, qui compte sur un premier passage.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const MIN_TAP_SIZE = 40;
const USER = 'user-fixture-51-workout@test.com';

async function findTooSmallTapTargets(page: Page): Promise<string[]> {
    return page.evaluate((minSize) => {
        const selector = 'a[href], button, input:not([type=hidden]), select, textarea, summary, label[for], [role=button]';
        const isTextFieldLabel = (el: Element): boolean => {
            if (el.tagName !== 'LABEL') {
                return false;
            }
            const field = document.getElementById(el.getAttribute('for') ?? '');

            return null !== field && !['checkbox', 'radio'].includes((field as HTMLInputElement).type);
        };
        const isInlineTextLink = (el: Element): boolean =>
            el.tagName === 'A' && null !== el.closest('p') && getComputedStyle(el).display === 'inline';
        const isVisuallyHidden = (el: Element, rect: DOMRect): boolean =>
            rect.width === 0 || rect.height === 0 || getComputedStyle(el).visibility === 'hidden' || null !== el.closest('.sr-only');

        return [...document.querySelectorAll(selector)].flatMap((el) => {
            const rect = el.getBoundingClientRect();
            if (isVisuallyHidden(el, rect) || isTextFieldLabel(el) || isInlineTextLink(el)) {
                return [];
            }
            if (rect.width >= minSize && rect.height >= minSize) {
                return [];
            }
            const name = ((el as HTMLElement).innerText || el.getAttribute('aria-label') || (el as HTMLInputElement).placeholder || '')
                .trim().replace(/\s+/g, ' ').slice(0, 40);

            return [`${Math.round(rect.width)}×${Math.round(rect.height)} <${el.tagName.toLowerCase()}> "${name}"`];
        });
    }, MIN_TAP_SIZE);
}

async function goto(page: Page, url: string): Promise<void> {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
}

async function firstHref(page: Page, listUrl: string, linkSelector: string): Promise<string> {
    await goto(page, listUrl);
    const href = await page.locator(linkSelector).first().getAttribute('href');
    if (null === href) {
        throw new Error(`No link matching ${linkSelector} on ${listUrl}`);
    }

    return href;
}

test.beforeEach(async ({ page }) => {
    await loginAs(page, USER);
});

for (const url of ['/fr/tableau-de-bord', '/fr/mes-seances', '/fr/bibliotheque', '/fr/mes-routines', '/fr/connexions', '/fr/mes-badges', '/fr/messagerie', '/fr/reglages']) {
    test(`tap targets are large enough on ${url}`, async ({ page }) => {
        await goto(page, url);

        expect(await findTooSmallTapTargets(page)).toEqual([]);
    });
}

test('tap targets are large enough on the workout calendar', async ({ page }) => {
    await goto(page, '/fr/mes-seances');
    await page.getByRole('link', { name: 'Calendrier' }).click();
    await page.waitForLoadState('networkidle');

    expect(await findTooSmallTapTargets(page)).toEqual([]);
});

test('tap targets are large enough in the open menu', async ({ page }) => {
    await goto(page, '/fr/tableau-de-bord');
    await page.getByRole('button', { name: 'Ouvrir le menu' }).click();
    await expect(page.getByRole('button', { name: 'Fermer le menu' })).toBeVisible();

    expect(await findTooSmallTapTargets(page)).toEqual([]);
});

test('tap targets are large enough when logging a workout', async ({ page }) => {
    await goto(page, '/fr/enregistre-seance');
    await addExercises(page, 1);

    expect(await findTooSmallTapTargets(page)).toEqual([]);
});

test('tap targets are large enough when editing a workout', async ({ page }) => {
    await goto(page, await firstHref(page, '/fr/mes-seances', 'a[href*="/modifier"]'));

    expect(await findTooSmallTapTargets(page)).toEqual([]);
});

test('tap targets are large enough on an exercise history', async ({ page }) => {
    await goto(page, await firstHref(page, '/fr/bibliotheque', 'a[href*="/historique"]'));

    expect(await findTooSmallTapTargets(page)).toEqual([]);
});
