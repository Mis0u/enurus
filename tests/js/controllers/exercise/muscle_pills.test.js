import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sweetalert2', () => ({ default: { fire: vi.fn() } }));

const Swal = (await import('sweetalert2')).default;
const {
    MusclePills,
    updatePillVisual,
    buildRecapHtml,
    renderMuscleRecap,
    syncMusclesInput,
    showFieldError,
    showDuplicateAlert,
} = await import('../../../../assets/controllers/exercise/muscle_pills.js');

function pillElement(muscleId, label, svgIds) {
    const el = document.createElement('div');
    el.dataset.muscleId = muscleId;
    el.dataset.muscleLabel = label;
    el.dataset.svgIds = JSON.stringify(svgIds);
    el.innerHTML = '<span data-role="dot" hidden></span><span data-role="badge" hidden></span>';

    return el;
}

describe('MusclePills', () => {
    let container;
    let pills;

    beforeEach(() => {
        container = document.createElement('div');
        container.innerHTML = '<g id="chest-1" class="bodymap"></g><g id="back-1" class="bodymap"></g>';

        const targets = [
            pillElement('chest', 'Chest', ['chest-1']),
            pillElement('back', 'Back', ['back-1']),
        ];

        pills = new MusclePills(container, targets);
    });

    it('defaults every pill to "none"', () => {
        expect(pills.get('chest')).toBe('none');
        expect(pills.get('unknown-id')).toBe('none');
    });

    it('cycles none -> primary -> secondary -> none', () => {
        expect(pills.cycle('chest')).toBe('primary');
        expect(pills.cycle('chest')).toBe('secondary');
        expect(pills.cycle('chest')).toBe('none');
    });

    it('hasPrimary reflects whether any pill is set to primary', () => {
        expect(pills.hasPrimary()).toBe(false);
        pills.set('chest', 'primary');
        expect(pills.hasPrimary()).toBe(true);
    });

    it('paintAll colors only the muscles with a non-none state', () => {
        pills.set('chest', 'primary');
        pills.paintAll();

        expect(container.querySelector('#chest-1').style.fill).toBe('#f43f5e');
        expect(container.querySelector('#back-1').style.fill).toBe('#1e293b');
    });

    it('musclesByState lists pills matching the given state with id and label', () => {
        pills.set('chest', 'primary');
        pills.set('back', 'secondary');

        expect(pills.musclesByState('primary')).toEqual([{ id: 'chest', label: 'Chest' }]);
        expect(pills.musclesByState('secondary')).toEqual([{ id: 'back', label: 'Back' }]);
    });

    it('toAssignments only includes muscles with a non-none state', () => {
        pills.set('chest', 'primary');

        expect(pills.toAssignments()).toEqual([{ id: 'chest', type: 'primary' }]);
    });
});

describe('updatePillVisual', () => {
    it('shows the dot and badge with the right letter for a primary state', () => {
        const pill = pillElement('chest', 'Chest', []);
        updatePillVisual(pill, 'primary');

        expect(pill.classList.contains('pill--primary')).toBe(true);
        expect(pill.querySelector('[data-role="dot"]').hidden).toBe(false);
        expect(pill.querySelector('[data-role="badge"]').textContent).toBe('P');
    });

    it('hides the dot and badge and clears classes for the none state', () => {
        const pill = pillElement('chest', 'Chest', []);
        updatePillVisual(pill, 'primary');
        updatePillVisual(pill, 'none');

        expect(pill.classList.contains('pill--primary')).toBe(false);
        expect(pill.classList.contains('pill--secondary')).toBe(false);
        expect(pill.querySelector('[data-role="dot"]').hidden).toBe(true);
        expect(pill.querySelector('[data-role="badge"]').hidden).toBe(true);
    });
});

describe('buildRecapHtml', () => {
    it('renders the "none" label when the muscle list is empty', () => {
        const html = buildRecapHtml([], 'primary', 'Aucun');

        expect(html).toContain('Aucun');
    });

    it('renders one recap pill per muscle with the right type class', () => {
        const html = buildRecapHtml([{ label: 'Chest' }, { label: 'Back' }], 'secondary', 'Aucun');

        expect(html).toContain('recap-pill--secondary');
        expect((html.match(/recap-pill/g) || []).length).toBe(4); // 2 fois "recap-pill" par span (classe base + type)
        expect(html).toContain('Chest');
        expect(html).toContain('Back');
    });
});

describe('renderMuscleRecap', () => {
    it('fills the primary and secondary recap targets independently', () => {
        const container = document.createElement('div');
        container.innerHTML = '<g id="chest-1" class="bodymap"></g>';
        const targets = [pillElement('chest', 'Chest', ['chest-1']), pillElement('back', 'Back', [])];
        const pills = new MusclePills(container, targets);
        pills.set('chest', 'primary');
        pills.set('back', 'secondary');

        const primaryTarget = document.createElement('div');
        const secondaryTarget = document.createElement('div');
        renderMuscleRecap(pills, primaryTarget, secondaryTarget, 'Aucun');

        expect(primaryTarget.textContent).toBe('Chest');
        expect(secondaryTarget.textContent).toBe('Back');
    });
});

describe('syncMusclesInput', () => {
    it('serializes the current assignments as JSON into the hidden input', () => {
        const container = document.createElement('div');
        const targets = [pillElement('chest', 'Chest', [])];
        const pills = new MusclePills(container, targets);
        pills.set('chest', 'primary');

        const input = document.createElement('input');
        syncMusclesInput(pills, input);

        expect(JSON.parse(input.value)).toEqual([{ id: 'chest', type: 'primary' }]);
    });
});

describe('showFieldError', () => {
    it('unhides the target and scrolls it into view', () => {
        const target = document.createElement('div');
        target.hidden = true;
        target.scrollIntoView = vi.fn();

        showFieldError(target);

        expect(target.hidden).toBe(false);
        expect(target.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'center' });
    });
});

describe('showDuplicateAlert', () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it('fires a SweetAlert2 warning with the given message', () => {
        showDuplicateAlert('Exercice déjà existant');

        expect(Swal.fire).toHaveBeenCalledWith(expect.objectContaining({
            icon: 'warning',
            text: 'Exercice déjà existant',
        }));
    });

    it('allows overriding default options', () => {
        showDuplicateAlert('Message', { confirmButtonColor: '#000000' });

        expect(Swal.fire).toHaveBeenCalledWith(expect.objectContaining({ confirmButtonColor: '#000000' }));
    });
});
