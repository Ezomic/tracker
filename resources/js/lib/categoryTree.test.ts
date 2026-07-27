import { describe, expect, it } from 'vitest';
import type { SidebarCategory } from '@/types';
import { visibleCategories } from './categoryTree';

function cat(
    id: number,
    parentId: number | null,
    name = `cat-${id}`,
): SidebarCategory {
    return { id, name, parentId, depth: 0, projects: [] };
}

// parent(1) > child(2) > grandchild(3), plus a top-level sibling(4).
const tree: SidebarCategory[] = [
    cat(1, null),
    cat(2, 1),
    cat(3, 2),
    cat(4, null),
];

const ids = (cats: SidebarCategory[]) => cats.map((c) => c.id);

describe('visibleCategories', () => {
    it('shows only top-level categories when nothing is expanded', () => {
        expect(ids(visibleCategories(tree, {}))).toEqual([1, 4]);
    });

    it('shows the whole tree when every ancestor is expanded', () => {
        expect(ids(visibleCategories(tree, { 1: true, 2: true }))).toEqual([
            1, 2, 3, 4,
        ]);
    });

    it('hides the entire subtree when a parent is collapsed', () => {
        // Parent collapsed: child and grandchild disappear even if they are
        // themselves expanded.
        expect(ids(visibleCategories(tree, { 1: false, 2: true }))).toEqual([
            1, 4,
        ]);
    });

    it('hides only deeper descendants when a mid-level category is collapsed', () => {
        expect(ids(visibleCategories(tree, { 1: true, 2: false }))).toEqual([
            1, 2, 4,
        ]);
    });
});
