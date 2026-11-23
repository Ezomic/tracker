import { describe, expect, it } from 'vitest';
import type {
    SidebarCategories,
    SidebarCategory,
    SidebarProject,
} from '@/types';
import { projectCount, sidebarSections } from './sidebarProjects';

function project(id: number, active: boolean): SidebarProject {
    return {
        id,
        key: `P${id}`,
        name: `Project ${id}`,
        color: '#000',
        active,
        counts: { backlog: 0, in_progress: 0, in_review: 0, done: 0 },
    };
}

function cat(
    id: number,
    parentId: number | null,
    projects: SidebarProject[] = [],
): SidebarCategory {
    return { id, name: `cat-${id}`, parentId, depth: 0, projects };
}

const ids = (projects: SidebarProject[]) => projects.map((p) => p.id);
const catIds = (cats: SidebarCategory[]) => cats.map((c) => c.id);

// parent(1) > child(2) holding an active project; sibling(3) only inactive;
// empty(4); uncategorized holds one active and one inactive project.
function categories(recent: SidebarProject[] = []): SidebarCategories {
    return {
        recent,
        tree: [
            cat(1, null),
            cat(2, 1, [project(10, true), project(11, false)]),
            cat(3, null, [project(12, false)]),
            cat(4, null),
        ],
        uncategorized: [project(13, true), project(14, false)],
    };
}

describe('sidebarSections', () => {
    it('shows only active projects by default', () => {
        const sections = sidebarSections(categories(), false);

        expect(ids(sections.tree.flatMap((c) => c.projects))).toEqual([10]);
        expect(ids(sections.uncategorized)).toEqual([13]);
    });

    it('hides categories with no visible projects but keeps their ancestors', () => {
        const sections = sidebarSections(categories(), false);

        expect(catIds(sections.tree)).toEqual([1, 2]);
    });

    it('always shows recent projects, even inactive ones', () => {
        const recent = [project(12, false)];
        const sections = sidebarSections(categories(recent), false);

        expect(ids(sections.recent)).toEqual([12]);
        expect(catIds(sections.tree)).toEqual([1, 2]);
    });

    it('leaves projects in recent out of the list below it', () => {
        const recent = [project(13, true), project(10, true)];
        const sections = sidebarSections(categories(recent), false);

        expect(ids(sections.recent)).toEqual([13, 10]);
        expect(sections.tree).toEqual([]);
        expect(sections.uncategorized).toEqual([]);
    });

    it('shows the full tree minus recent projects when showing all', () => {
        const recent = [project(11, false)];
        const sections = sidebarSections(categories(recent), true);

        expect(catIds(sections.tree)).toEqual([1, 2, 3, 4]);
        expect(ids(sections.tree[1].projects)).toEqual([10]);
        expect(ids(sections.tree[2].projects)).toEqual([12]);
        expect(ids(sections.uncategorized)).toEqual([13, 14]);
    });

    it('counts the projects the default view hides', () => {
        expect(sidebarSections(categories(), false).hidden).toBe(3);
        expect(
            sidebarSections(categories([project(11, false)]), false).hidden,
        ).toBe(2);
        expect(sidebarSections(categories(), true).hidden).toBe(0);
    });
});

describe('projectCount', () => {
    it('counts every project in the tree and uncategorized', () => {
        expect(projectCount(categories())).toBe(5);
    });
});
