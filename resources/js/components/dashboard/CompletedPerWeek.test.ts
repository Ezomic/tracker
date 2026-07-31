import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import type { CompletedByWeek } from '@/types';
import CompletedPerWeek from './CompletedPerWeek.vue';

function data(overrides: Partial<CompletedByWeek> = {}): CompletedByWeek {
    return {
        weeks: ['Jun 9', 'Jun 16'],
        series: [
            {
                key: 'TRACK',
                name: 'Tracker',
                color: '#d85a30',
                values: [1, 3],
                total: 4,
                other: false,
            },
            {
                key: 'other',
                name: 'Other (2)',
                color: null,
                values: [0, 1],
                total: 1,
                other: true,
            },
        ],
        weekTotals: [1, 4],
        grandTotal: 5,
        ...overrides,
    };
}

function mountChart(payload: CompletedByWeek) {
    return mount(CompletedPerWeek, {
        props: { data: payload },
        global: { plugins: [i18n] },
    });
}

describe('CompletedPerWeek', () => {
    beforeEach(() => setLocale('en'));

    it('shows the empty state when nothing was completed', () => {
        const text = mountChart(
            data({ series: [], weekTotals: [0, 0], grandTotal: 0 }),
        ).text();
        expect(text).toContain('No tickets completed in this window yet.');
    });

    it('renders a legend entry per series, including Other', () => {
        const text = mountChart(data()).text();
        expect(text).toContain('Tracker');
        expect(text).toContain('Other (2)');
    });

    it('computes the week-over-week comparison from the totals', () => {
        // 4 this week vs 1 previous week is a +300% swing.
        expect(mountChart(data()).text()).toContain('+300%');
    });

    it('renders a bar segment for every non-zero series value', () => {
        const segments = mountChart(data()).findAll('[title]');
        // TRACK has values in both weeks (2), Other only in the second week (1).
        expect(segments).toHaveLength(3);
    });
});
