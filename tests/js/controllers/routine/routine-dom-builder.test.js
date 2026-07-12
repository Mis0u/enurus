import { describe, expect, it } from 'vitest';
import { buildErrorElement, buildSelectedItem } from '../../../../assets/controllers/routine/routine-dom-builder.js';

// Même implémentation que le #escape privé de create_controller.js / edit_controller.js.
function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

describe('buildSelectedItem', () => {
    it('escapes the exercise name and muscle tags to prevent XSS', () => {
        const data = {
            name: '<img src=x onerror=alert(1)>',
            primaryMuscles: ['<script>alert(1)</script>'],
            secondaryMuscles: [],
        };

        const el = buildSelectedItem('ex-1', data, 1, 'routine--create', escapeHtml);

        expect(el.innerHTML).not.toContain('<img src=x');
        expect(el.innerHTML).not.toContain('<script>');
        expect(el.querySelector('p').textContent).toBe('<img src=x onerror=alert(1)>');
    });

    it('wires the position, exercise id and remove action for the controller', () => {
        const data = { name: 'Bench press', primaryMuscles: ['Chest'], secondaryMuscles: ['Triceps'] };

        const el = buildSelectedItem('ex-42', data, 3, 'routine--edit', escapeHtml);

        expect(el.dataset.exerciseId).toBe('ex-42');
        expect(el.querySelector('[data-routine--edit-target="itemPosition"]').textContent).toBe('#3');

        const removeButton = el.querySelector('button');
        expect(removeButton.dataset.action).toBe('click->routine--edit#removeExercise');
        expect(removeButton.dataset.exerciseId).toBe('ex-42');
    });

    it('renders one tag per muscle with the right type', () => {
        const data = { name: 'Squat', primaryMuscles: ['Quadriceps', 'Glutes'], secondaryMuscles: ['Hamstrings'] };

        const el = buildSelectedItem('ex-7', data, 1, 'routine--create', escapeHtml);

        const primaryTags = el.querySelectorAll('[data-muscle-type="primary"]');
        const secondaryTags = el.querySelectorAll('[data-muscle-type="secondary"]');

        expect(primaryTags).toHaveLength(2);
        expect(secondaryTags).toHaveLength(1);
        expect(secondaryTags[0].textContent).toBe('Hamstrings');
    });
});

describe('buildErrorElement', () => {
    it('builds an inline error message element', () => {
        const el = buildErrorElement('Name already taken');

        expect(el.classList.contains('field-error')).toBe(true);
        expect(el.querySelector('span').textContent).toBe('Name already taken');
    });
});
