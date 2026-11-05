import { describe, expect, it } from 'vitest';
import { estimateOneRepMax, trainingLoads } from '../../../assets/utils/one_rep_max.js';
import cases from '../../shared/one_rep_max_cases.json';

describe('estimateOneRepMax', () => {
    it.each(cases.estimates)('estimates $weight × $reps at $oneRepMax', ({ weight, reps, oneRepMax }) => {
        expect(estimateOneRepMax(weight, reps)).toBeCloseTo(oneRepMax, 2);
    });

    it.each(cases.rejected)('gives no estimate for $weight × $reps', ({ weight, reps }) => {
        expect(estimateOneRepMax(weight, reps)).toBeNull();
    });
});

describe('trainingLoads', () => {
    it('goes from 100 % down to 60 % of the one-rep max', () => {
        const percents = trainingLoads(100, 2.5).map((row) => row.percent);

        expect(percents).toEqual([100, 95, 90, 85, 80, 75, 70, 65, 60]);
    });

    it('rounds each load to the nearest plate step', () => {
        const loads = trainingLoads(96, 2.5).map((row) => row.load);

        expect(loads).toEqual([95, 90, 87.5, 82.5, 77.5, 72.5, 67.5, 62.5, 57.5]);
    });

    it('rounds pounds to 5 lbs', () => {
        expect(trainingLoads(212, 5)[0].load).toBe(210);
    });

    it('gives the reps the same formula allows at each percentage', () => {
        const reps = trainingLoads(100, 2.5).map((row) => row.reps);

        expect(reps).toEqual([1, 2, 3, 5, 8, 10, 13, 16, 20]);
    });
});
