import { test, expect, devices, type Page } from '@playwright/test';
import { loginAs } from './helpers';

// Bas de page mobile : `<main>` réserve déjà la place de la barre du bas (`max-md:pb-20`), chaque
// page n'ajoute qu'un petit padding (`max-md:pb-4`, `max-md:pb-20` sous une barre d'action fixe).
// Garde-fou dans les deux sens : le dernier élément ne passe jamais sous la barre du bas, le « + »
// central ou la barre « Valider », et aucun grand vide ne reste en dessous (170 px avant correction).
// Lecture seule sur `fixture-51` (jamais `fixture-26` : sa synchro de badges pollue `BadgeFlowTest`).
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const USER = 'user-fixture-51-workout@test.com';
const MAX_EMPTY_SPACE_PX = 40;

type BottomSpace = { contentBottom: number; obstacleTop: number } | null;

// Défile tout en bas de la zone de scroll de la page, puis compare le bas du dernier élément visible
// au haut du premier obstacle fixe (barre du bas, « + » qui la dépasse, boutons d'une barre d'action).
// `null` si la page tient dans l'écran : pas de bas de page à vérifier.
async function measureBottomSpace(page: Page): Promise<BottomSpace> {
    return page.evaluate(() => {
        const scroller = [...document.querySelectorAll<HTMLElement>('main *')]
            .filter((element) => /(auto|scroll)/.test(getComputedStyle(element).overflowY))
            .filter((element) => element.scrollHeight > element.clientHeight)
            .sort((a, b) => b.clientHeight - a.clientHeight)[0];

        if (! scroller) {
            return null;
        }

        // `instant` : un défilement doux (`scroll-behavior: smooth`) ne serait pas fini à la mesure.
        scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'instant' });

        // Bas visible d'un élément : un conteneur sans fond ni bordure ne montre pas son padding
        // (c'est justement le vide mesuré), une carte le montre.
        const visibleBottom = (element: Element) => {
            const box = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            const isPainted = 'rgba(0, 0, 0, 0)' !== style.backgroundColor || 'none' !== style.backgroundImage || 0 < parseFloat(style.borderBottomWidth);

            return isPainted ? box.bottom : box.bottom - parseFloat(style.paddingBottom);
        };
        const contentBottom = Math.max(...[...scroller.querySelectorAll('*')]
            .filter((element) => null === element.closest('.fixed'))
            // Chrome positionne le contenu d'un <details> fermé sans l'afficher : seul son titre compte.
            .filter((element) => null === element.closest('details:not([open])') || null !== element.closest('summary'))
            .filter((element) => { const box = element.getBoundingClientRect(); return 0 < box.width && 0 < box.height; })
            .map(visibleBottom));

        const bottomNav = document.querySelector('nav[aria-label="Navigation principale"]');
        const obstacles = [
            bottomNav,
            document.querySelector('.bottom-nav-cta'),
            ...document.querySelectorAll('main .fixed button, main .fixed a'),
        ].filter((element): element is Element => null !== element)
            .map((element) => element.getBoundingClientRect())
            .filter((box) => 0 < box.height);

        return { contentBottom, obstacleTop: Math.min(...obstacles.map((box) => box.top)) };
    });
}

async function firstWorkoutUrl(page: Page): Promise<string> {
    await page.goto('/fr/mes-seances');
    const href = await page.locator('main a[href^="/fr/seance/"]').first().getAttribute('href');
    expect(href).not.toBeNull();

    return href!;
}

async function expectTidyBottom(page: Page, url: string): Promise<void> {
    await page.goto(url);
    await page.waitForLoadState('networkidle');

    const space = await measureBottomSpace(page);
    test.skip(null === space, `${url} tient dans l'écran`);

    expect(space!.contentBottom, `${url} : contenu masqué en bas`).toBeLessThanOrEqual(space!.obstacleTop);
    expect(space!.obstacleTop - space!.contentBottom, `${url} : vide en bas de page`).toBeLessThanOrEqual(MAX_EMPTY_SPACE_PX);
}

test.beforeEach(async ({ page }) => {
    await loginAs(page, USER);
});

for (const url of ['/fr/tableau-de-bord', '/fr/mes-seances', '/fr/mes-badges', '/fr/aide', '/fr/reglages']) {
    test(`${url} ends right above the bottom bar`, async ({ page }) => {
        await expectTidyBottom(page, url);
    });
}

test('a workout page ends right above the bottom bar', async ({ page }) => {
    await expectTidyBottom(page, await firstWorkoutUrl(page));
});

// Barre « Valider » fixe au-dessus de la barre du bas : le formulaire doit s'arrêter juste au-dessus.
test('the workout edit form ends right above its action bar', async ({ page }) => {
    const workoutUrl = await firstWorkoutUrl(page);
    const editUrl = await page.goto(workoutUrl).then(() => page.locator('a[href$="/modifier"]').first().getAttribute('href'));
    expect(editUrl).not.toBeNull();

    await expectTidyBottom(page, editUrl!);
});
