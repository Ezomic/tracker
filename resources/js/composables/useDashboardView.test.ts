import { beforeEach, describe, expect, it } from 'vitest';
import { isDashboardView, useDashboardView } from './useDashboardView';

describe('isDashboardView', () => {
    it('accepts the known views and rejects anything else', () => {
        expect(isDashboardView('focus')).toBe(true);
        expect(isDashboardView('metrics')).toBe(true);
        expect(isDashboardView('board')).toBe(true);
        expect(isDashboardView('list')).toBe(false);
        expect(isDashboardView(null)).toBe(false);
        expect(isDashboardView(3)).toBe(false);
    });
});

describe('useDashboardView', () => {
    beforeEach(() => {
        localStorage.clear();
        useDashboardView().setView('focus');
    });

    it('defaults to focus', () => {
        expect(useDashboardView().view.value).toBe('focus');
    });

    it('setView updates the shared value and persists it', () => {
        const { view, setView } = useDashboardView();

        setView('board');

        expect(view.value).toBe('board');
        expect(localStorage.getItem('dashboard-view')).toBe('board');
        // shared across call sites
        expect(useDashboardView().view.value).toBe('board');
    });
});
