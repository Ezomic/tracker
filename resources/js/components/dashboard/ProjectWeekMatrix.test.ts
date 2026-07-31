import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import type { CompletedByWeek } from '@/types';
import ProjectWeekMatrix from './ProjectWeekMatrix.vue';

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

function mountMatrix(payload: CompletedByWeek) {
    return mount(ProjectWeekMatrix, {
        props: { data: payload },
        global: { plugins: [i18n] },
    });
}

describe('ProjectWeekMatrix', () => {
    beforeEach(() => setLocale('en'));

    it('shows the empty state when nothing was completed', () => {
        expect(
            mountMatrix(
                data({ series: [], weekTotals: [0, 0], grandTotal: 0 }),
            ).text(),
        ).toContain('No tickets completed in this window yet.');
    });

    it('renders a row per series with its total', () => {
        const rows = mountMatrix(data()).findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Tracker');
        expect(rows[0].text()).toContain('4');
        expect(rows[1].text()).toContain('Other (2)');
    });

    it('renders week and grand totals in the footer', () => {
        const footer = mountMatrix(data()).find('tfoot tr').text();
        expect(footer).toContain('1');
        expect(footer).toContain('4');
        expect(footer).toContain('5');
    });

    it('shades non-zero cells and leaves zero cells unshaded', () => {
        const cells = mountMatrix(data()).findAll('tbody tr td span');
        // The Other series has a 0 in the first week: that heat span has no background.
        const zeroCell = cells.find((cell) => cell.text() === '0');
        expect(zeroCell?.attributes('style') ?? '').not.toContain(
            'background-color',
        );
    });
});
