import type { Issue } from '@/types';

/** Mid-tone hues that read on both the light and the dark card background. */
export const EPIC_PALETTE = [
    '#6366f1',
    '#f59e0b',
    '#10b981',
    '#ec4899',
    '#0ea5e9',
    '#8b5cf6',
    '#ef4444',
    '#14b8a6',
    '#f97316',
    '#84cc16',
] as const;

export function epicColor(id: number): string {
    return EPIC_PALETTE[id % EPIC_PALETTE.length];
}

export interface EpicLane {
    key: string;
    label: string;
    color: string | null;
    colorClass: string;
    match: (issue: Issue) => boolean;
}

export interface EpicSegment {
    key: string;
    label: string;
    color: string | null;
    colorClass: string;
    count: number;
}

export interface EpicSummary {
    id: number;
    identifier: string;
    title: string;
    /** Null when the epic itself is not on the board, only its children. */
    project: Issue['project'] | null;
    color: string;
    archived: boolean;
    done: number;
    total: number;
    segments: EpicSegment[];
}

export type EpicFocus = number | 'none' | null;

const OTHER_LANE = 'other';

export function isEpic(issue: Issue): boolean {
    return issue.childrenCount > 0;
}

/**
 * Every epic the board knows about, with progress counted from the children
 * that are on the board right now, bucketed by the same lanes as the columns.
 */
export function summarizeEpics(
    issues: Issue[],
    lanes: EpicLane[],
): EpicSummary[] {
    const epics = new Map<number, EpicSummary>();
    const childrenOf = new Map<number, Issue[]>();

    for (const issue of issues) {
        if (isEpic(issue)) {
            epics.set(
                issue.id,
                blankSummary(issue, issue.project, issue.archivedAt !== null),
            );
        } else if (issue.parent) {
            if (!epics.has(issue.parent.id)) {
                epics.set(
                    issue.parent.id,
                    blankSummary(issue.parent, null, false),
                );
            }

            const siblings = childrenOf.get(issue.parent.id) ?? [];
            siblings.push(issue);
            childrenOf.set(issue.parent.id, siblings);
        }
    }

    for (const epic of epics.values()) {
        const children = childrenOf.get(epic.id) ?? [];
        epic.total = children.length;
        epic.done = children.filter((child) => child.status === 'done').length;
        epic.segments = segmentsFor(children, lanes);
    }

    return [...epics.values()];
}

function blankSummary(
    epic: { id: number; identifier: string; title: string },
    project: Issue['project'] | null,
    archived: boolean,
): EpicSummary {
    return {
        id: epic.id,
        identifier: epic.identifier,
        title: epic.title,
        project,
        color: epicColor(epic.id),
        archived,
        done: 0,
        total: 0,
        segments: [],
    };
}

function segmentsFor(children: Issue[], lanes: EpicLane[]): EpicSegment[] {
    const segments: EpicSegment[] = lanes.map((lane) => ({
        key: lane.key,
        label: lane.label,
        color: lane.color,
        colorClass: lane.colorClass,
        count: children.filter(lane.match).length,
    }));
    const other = children.filter(
        (child) => !lanes.some((lane) => lane.match(child)),
    ).length;

    segments.push({
        key: OTHER_LANE,
        label: OTHER_LANE,
        color: null,
        colorClass: 'bg-muted-foreground/30',
        count: other,
    });

    return segments.filter((segment) => segment.count > 0);
}

export function epicProgress(
    epic: Pick<EpicSummary, 'done' | 'total'>,
): 'open' | 'finished' | 'empty' {
    if (epic.total === 0) {
        return 'empty';
    }

    return epic.done === epic.total ? 'finished' : 'open';
}

export function panelEpics(
    epics: EpicSummary[],
    showAll: boolean,
    focus: EpicFocus = null,
): EpicSummary[] {
    // The focused epic stays listed, so finishing its last open child does
    // not hide the row that clears the filter.
    return showAll
        ? epics
        : epics.filter(
              (epic) => epicProgress(epic) === 'open' || epic.id === focus,
          );
}

export function matchesEpicFocus(issue: Issue, focus: EpicFocus): boolean {
    if (focus === null) {
        return true;
    }

    if (focus === 'none') {
        return issue.parent === null && !isEpic(issue);
    }

    return issue.parent?.id === focus;
}

export interface EpicProjectGroup {
    key: string;
    name: string;
    epics: EpicSummary[];
}

export function groupEpicsByProject(epics: EpicSummary[]): EpicProjectGroup[] {
    const groups = new Map<string, EpicProjectGroup>();

    for (const epic of epics) {
        const key = epic.project?.key ?? '';
        const group = groups.get(key) ?? {
            key,
            name: epic.project?.name ?? '',
            epics: [],
        };
        group.epics.push(epic);
        groups.set(key, group);
    }

    return [...groups.values()];
}

export type EpicDropTarget = EpicSummary | 'none';

// A type alias, not an interface, so it satisfies Inertia's payload type.
export type ParentChange = { parent: string | null };

/**
 * Mirrors the server rules a board can check, so a refused row fades instead
 * of sending a request. Epics may span projects, and every row on the board
 * is in the board's organization, so the server keeps the organization check.
 */
export function canDropOnEpic(card: Issue, target: EpicDropTarget): boolean {
    // Its epic is unknown here, so neither the no-op check nor Undo would
    // know what to restore.
    if (card.parentOffBoard) {
        return false;
    }

    if (target === 'none') {
        return true;
    }

    return !isEpic(card) && target.id !== card.id;
}

/** The request body for a drop, or null when it would change nothing. */
export function parentAfterDrop(
    card: Issue,
    target: EpicDropTarget,
): ParentChange | null {
    if (!canDropOnEpic(card, target)) {
        return null;
    }

    const parent = target === 'none' ? null : target.identifier;

    return parent === (card.parent?.identifier ?? null) ? null : { parent };
}

export function undoParentChange(card: Issue): ParentChange {
    return { parent: card.parent?.identifier ?? null };
}
