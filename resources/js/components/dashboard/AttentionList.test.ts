import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import type { IssueRow } from '@/types';
import AttentionList from './AttentionList.vue';

function row(overrides: Partial<IssueRow> = {}): IssueRow {
    return {
        identifier: 'SHOP-1',
        title: 'A ticket',
        projectName: 'Shop',
        projectColor: '#d85a30',
        status: 'in_progress',
        ageDays: 2,
        stale: false,
        timestamp: null,
        ...overrides,
    };
}

function mountList(rows: IssueRow[]) {
    return mount(AttentionList, {
        props: { rows },
        global: { plugins: [i18n] },
    });
}

describe('AttentionList', () => {
    beforeEach(() => setLocale('en'));

    it('shows the empty state when there are no rows', () => {
        expect(mountList([]).text()).toContain('Nothing needs you right now.');
    });

    it('labels a fresh row with a relative age', () => {
        expect(mountList([row({ ageDays: 3 })]).text()).toContain('3d ago');
    });

    it('labels a zero-age row as today', () => {
        expect(mountList([row({ ageDays: 0 })]).text()).toContain('today');
    });

    it('flags a stale row with an idle label and the Stale chip', () => {
        const text = mountList([row({ stale: true, ageDays: 12 })]).text();
        expect(text).toContain('12d idle');
        expect(text).toContain('Stale');
    });

    it('renders the status label for a non-stale row', () => {
        expect(
            mountList([row({ status: 'in_review', stale: false })]).text(),
        ).toContain('In review');
    });
});
