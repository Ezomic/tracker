import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import type {
    BoardColumns,
    DashboardMetrics,
    IssueRow,
    StatusBreakdown,
} from '@/types';
import BoardView from './BoardView.vue';

function card(overrides: Partial<IssueRow> = {}): IssueRow {
    return {
        identifier: 'SHOP-1',
        title: 'A ticket',
        projectName: 'Shop',
        projectColor: '#d85a30',
        status: 'backlog',
        ageDays: 1,
        stale: false,
        timestamp: null,
        ...overrides,
    };
}

const emptyBoard: BoardColumns = {
    backlog: [],
    in_progress: [],
    in_review: [],
    done: [],
};

const breakdown: StatusBreakdown = {
    backlog: 3,
    in_progress: 2,
    in_review: 1,
    done: 4,
};

const metrics = { completed: 7 } as DashboardMetrics;

function mountBoard(board: BoardColumns) {
    return mount(BoardView, {
        props: { board, statusBreakdown: breakdown, metrics },
        global: { plugins: [i18n] },
    });
}

describe('BoardView', () => {
    beforeEach(() => setLocale('en'));

    it('sums the at-risk count across every column', () => {
        const board: BoardColumns = {
            ...emptyBoard,
            backlog: [card({ stale: true }), card({ identifier: 'SHOP-2' })],
            in_review: [card({ identifier: 'SHOP-3', stale: true })],
        };

        const atRisk = mountBoard(board)
            .findAll('span.rounded-full')
            .find((el) => el.text().includes('At risk'));

        expect(atRisk?.text()).toContain('2');
    });

    it('renders the done-this-week pill from metrics.completed', () => {
        const pills = mountBoard(emptyBoard).text();
        expect(pills).toContain('Done this week');
        expect(pills).toContain('7');
    });

    it('marks a stale card with an amber left edge', () => {
        const board: BoardColumns = {
            ...emptyBoard,
            backlog: [card({ stale: true })],
        };
        expect(mountBoard(board).html()).toContain('border-l-amber-500');
    });
});
