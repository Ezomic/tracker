import type {
    SidebarCategories,
    SidebarCategory,
    SidebarProject,
} from '@/types';

export interface SidebarSections {
    recent: SidebarProject[];
    tree: SidebarCategory[];
    uncategorized: SidebarProject[];
    /** Projects the default view leaves out (0 when showing all). */
    hidden: number;
}

/**
 * A category is kept while a descendant still holds a project, because the
 * tree renders as a flat depth-first list and a child row needs its parent.
 */
export function sidebarSections(
    categories: SidebarCategories,
    showAll: boolean,
): SidebarSections {
    const inRecent = new Set(categories.recent.map((project) => project.id));
    let hidden = 0;

    const keep = (projects: SidebarProject[]): SidebarProject[] =>
        projects.filter((project) => {
            if (inRecent.has(project.id)) {
                return false;
            }

            if (showAll || project.active) {
                return true;
            }

            hidden++;

            return false;
        });

    const tree = categories.tree.map((category) => ({
        ...category,
        projects: keep(category.projects),
    }));
    const uncategorized = keep(categories.uncategorized);

    return {
        recent: categories.recent,
        tree: showAll ? tree : withoutEmptyCategories(tree),
        uncategorized,
        hidden,
    };
}

function withoutEmptyCategories(tree: SidebarCategory[]): SidebarCategory[] {
    const parentOf = new Map(
        tree.map((category) => [category.id, category.parentId]),
    );
    const filled = new Set<number>();

    for (const category of tree) {
        let cursor: number | null =
            category.projects.length > 0 ? category.id : null;

        while (cursor !== null && !filled.has(cursor)) {
            filled.add(cursor);
            cursor = parentOf.get(cursor) ?? null;
        }
    }

    return tree.filter((category) => filled.has(category.id));
}

/** Every project in the sidebar; Recent only repeats some of them. */
export function projectCount(categories: SidebarCategories): number {
    return (
        categories.tree.reduce(
            (sum, category) => sum + category.projects.length,
            0,
        ) + categories.uncategorized.length
    );
}
