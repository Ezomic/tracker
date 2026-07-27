import type { SidebarCategory } from '@/types';

/**
 * The sidebar renders the category tree as a flat, depth-first list, so a child
 * category is a sibling row of its parent rather than nested inside it. A
 * category is visible only when its entire ancestor chain is expanded, so
 * collapsing a parent hides its whole subtree.
 */
export function visibleCategories(
    tree: SidebarCategory[],
    expanded: Record<number, boolean>,
): SidebarCategory[] {
    const parentOf = new Map(
        tree.map((category) => [category.id, category.parentId]),
    );

    return tree.filter((category) => {
        let parentId = category.parentId;

        while (parentId !== null) {
            if (!(expanded[parentId] ?? false)) {
                return false;
            }

            parentId = parentOf.get(parentId) ?? null;
        }

        return true;
    });
}
