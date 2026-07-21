import { describe, expect, it } from 'vitest';
import { staleChipClass, statusChipClass, statusDotClass } from './issueStatus';

describe('issueStatus class maps', () => {
    it('maps every status to a dot and chip class', () => {
        for (const status of [
            'backlog',
            'in_progress',
            'in_review',
            'done',
        ] as const) {
            expect(statusDotClass[status]).toBeTruthy();
            expect(statusChipClass[status]).toBeTruthy();
        }
    });

    it('gives in_progress the coral accent and done the emerald one', () => {
        expect(statusDotClass.in_progress).toContain('primary');
        expect(statusDotClass.done).toContain('emerald');
        expect(staleChipClass).toContain('amber');
    });
});
