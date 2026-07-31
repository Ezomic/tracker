import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import type { ActiveByProject } from '@/types';
import ActiveProjects from './ActiveProjects.vue';

function project(overrides: Partial<ActiveByProject> = {}): ActiveByProject {
    return {
        key: 'SHOP',
        name: 'Shop',
        color: '#d85a30',
        count: 4,
        other: false,
        ...overrides,
    };
}

function mountList(projects: ActiveByProject[]) {
    return mount(ActiveProjects, {
        props: { projects },
        global: { plugins: [i18n] },
    });
}

describe('ActiveProjects', () => {
    beforeEach(() => setLocale('en'));

    it('shows the empty state when nothing is active', () => {
        expect(mountList([]).text()).toContain('No active tickets.');
    });

    it('renders the summed total in the centre', () => {
        const text = mountList([
            project({ key: 'A', name: 'Alpha', count: 5 }),
            project({ key: 'B', name: 'Beta', count: 3 }),
        ]).text();
        expect(text).toContain('8');
    });

    it('renders a legend row per project including an Other bucket', () => {
        const text = mountList([
            project({ key: 'A', name: 'Alpha', count: 5 }),
            project({
                key: 'other',
                name: 'Other (3)',
                color: null,
                count: 6,
                other: true,
            }),
        ]).text();
        expect(text).toContain('Alpha');
        expect(text).toContain('Other (3)');
        // Total = 5 + 6.
        expect(text).toContain('11');
    });

    it('does not crash on a null-coloured Other segment', () => {
        const wrapper = mountList([
            project({ key: 'A', name: 'Alpha', count: 2 }),
            project({
                key: 'other',
                name: 'Other (1)',
                color: null,
                count: 1,
                other: true,
            }),
        ]);
        // Two donut arcs rendered (plus the background track circle).
        expect(wrapper.findAll('circle').length).toBe(3);
    });
});
