import { describe, expect, it } from 'vitest';
import type { Issue } from '@/types';
import type { EpicLane } from './epics';
import {
    canDropOnEpic,
    EPIC_PALETTE,
    epicColor,
    epicProgress,
    groupEpicsByProject,
    isEpic,
    matchesEpicFocus,
    panelEpics,
    parentAfterDrop,
    summarizeEpics,
    undoParentChange,
} from './epics';

let sequence = 0;

function issue(overrides: Partial<Issue> = {}): Issue {
    sequence += 1;

    return {
        id: sequence,
        identifier: `TST-${sequence}`,
        title: `Issue ${sequence}`,
        status: 'backlog',
        workflowStateId: null,
        project: { key: 'TST', name: 'Test' },
        archivedAt: null,
        childrenCount: 0,
        parent: null,
        ...overrides,
    } as Issue;
}

function epic(overrides: Partial<Issue> = {}): Issue {
    return issue({ childrenCount: 1, ...overrides });
}

function childOf(parent: Issue, overrides: Partial<Issue> = {}): Issue {
    return issue({
        parent: {
            id: parent.id,
            identifier: parent.identifier,
            title: parent.title,
        },
        ...overrides,
    });
}

const legacyLanes: EpicLane[] = (
    ['backlog', 'in_progress', 'in_review', 'done'] as const
).map((status) => ({
    key: status,
    label: status,
    color: null,
    colorClass: `dot-${status}`,
    match: (candidate: Issue) => candidate.status === status,
}));

const stateLanes: EpicLane[] = [
    { id: 10, color: '#aaaaaa' },
    { id: 11, color: '#bbbbbb' },
].map((state) => ({
    key: `state-${state.id}`,
    label: `State ${state.id}`,
    color: state.color,
    colorClass: '',
    match: (candidate: Issue) => candidate.workflowStateId === state.id,
}));

describe('epicColor', () => {
    it('is stable for the same id', () => {
        expect(epicColor(42)).toBe(epicColor(42));
    });

    it('walks the palette in order and wraps around', () => {
        expect(epicColor(0)).toBe(EPIC_PALETTE[0]);
        expect(epicColor(1)).toBe(EPIC_PALETTE[1]);
        expect(epicColor(EPIC_PALETTE.length)).toBe(EPIC_PALETTE[0]);
        expect(epicColor(EPIC_PALETTE.length + 3)).toBe(EPIC_PALETTE[3]);
    });

    it('gives neighbouring ids different colors', () => {
        expect(epicColor(7)).not.toBe(epicColor(8));
    });

    it('only uses distinct hex colors', () => {
        expect(new Set(EPIC_PALETTE).size).toBe(EPIC_PALETTE.length);

        for (const color of EPIC_PALETTE) {
            expect(color).toMatch(/^#[0-9a-f]{6}$/);
        }
    });
});

describe('isEpic', () => {
    it('is true only for issues with sub-issues', () => {
        expect(isEpic(issue())).toBe(false);
        expect(isEpic(issue({ childrenCount: 3 }))).toBe(true);
    });
});

describe('summarizeEpics', () => {
    it('counts done and total from the children on the board', () => {
        const parent = epic({ childrenCount: 3 });
        const issues = [
            parent,
            childOf(parent, { status: 'done' }),
            childOf(parent, { status: 'in_progress' }),
            childOf(parent, { status: 'backlog' }),
        ];

        const [summary] = summarizeEpics(issues, legacyLanes);

        expect(summary).toMatchObject({
            id: parent.id,
            identifier: parent.identifier,
            title: parent.title,
            project: parent.project,
            color: epicColor(parent.id),
            archived: false,
            done: 1,
            total: 3,
        });
    });

    it('splits the bar by the lanes in column order, skipping empty lanes', () => {
        const parent = epic();
        const issues = [
            childOf(parent, { status: 'done' }),
            childOf(parent, { status: 'backlog' }),
            childOf(parent, { status: 'done' }),
            parent,
        ];

        const [summary] = summarizeEpics(issues, legacyLanes);

        expect(summary.segments).toEqual([
            {
                key: 'backlog',
                label: 'backlog',
                color: null,
                colorClass: 'dot-backlog',
                count: 1,
            },
            {
                key: 'done',
                label: 'done',
                color: null,
                colorClass: 'dot-done',
                count: 2,
            },
        ]);
    });

    it('buckets by workflow state, not the legacy status', () => {
        const parent = epic();
        const issues = [
            parent,
            childOf(parent, { workflowStateId: 11, status: 'backlog' }),
            childOf(parent, { workflowStateId: 11, status: 'in_review' }),
        ];

        const [summary] = summarizeEpics(issues, stateLanes);

        expect(summary.segments).toEqual([
            {
                key: 'state-11',
                label: 'State 11',
                color: '#bbbbbb',
                colorClass: '',
                count: 2,
            },
        ]);
    });

    it('puts children that match no lane in a trailing other segment', () => {
        const parent = epic();
        const issues = [
            parent,
            childOf(parent, { workflowStateId: 10 }),
            childOf(parent, { workflowStateId: 99 }),
        ];

        const [summary] = summarizeEpics(issues, stateLanes);

        expect(summary.segments.map((s) => [s.key, s.count])).toEqual([
            ['state-10', 1],
            ['other', 1],
        ]);
        expect(summary.total).toBe(2);
    });

    it('lists an epic with no children on the board as empty', () => {
        const parent = epic({ childrenCount: 2 });

        const [summary] = summarizeEpics([parent], legacyLanes);

        expect(summary).toMatchObject({ done: 0, total: 0, segments: [] });
    });

    it('builds an epic that is not on the board from its children', () => {
        const absent = issue({ title: 'Elsewhere' });
        const issues = [childOf(absent), childOf(absent, { status: 'done' })];

        const summaries = summarizeEpics(issues, legacyLanes);

        expect(summaries).toHaveLength(1);
        expect(summaries[0]).toMatchObject({
            id: absent.id,
            identifier: absent.identifier,
            title: 'Elsewhere',
            project: null,
            archived: false,
            done: 1,
            total: 2,
        });
    });

    it('keeps the board order of the epics', () => {
        const first = epic();
        const second = epic();
        const absent = issue();

        const summaries = summarizeEpics(
            [childOf(absent), second, first],
            legacyLanes,
        );

        expect(summaries.map((s) => s.id)).toEqual([
            absent.id,
            second.id,
            first.id,
        ]);
    });

    it('marks an archived epic', () => {
        const [summary] = summarizeEpics(
            [epic({ archivedAt: '2026-01-01T00:00:00+00:00' })],
            legacyLanes,
        );

        expect(summary.archived).toBe(true);
    });

    it('ignores ordinary issues', () => {
        expect(summarizeEpics([issue(), issue()], legacyLanes)).toEqual([]);
    });
});

describe('epicProgress', () => {
    it('reads open, finished and empty', () => {
        expect(epicProgress({ done: 1, total: 3 })).toBe('open');
        expect(epicProgress({ done: 3, total: 3 })).toBe('finished');
        expect(epicProgress({ done: 0, total: 0 })).toBe('empty');
    });
});

describe('panelEpics', () => {
    const parentOpen = epic();
    const parentDone = epic();
    const parentEmpty = epic();
    const summaries = summarizeEpics(
        [
            parentOpen,
            childOf(parentOpen, { status: 'done' }),
            childOf(parentOpen),
            parentDone,
            childOf(parentDone, { status: 'done' }),
            parentEmpty,
        ],
        legacyLanes,
    );

    it('shows only epics with open work by default', () => {
        expect(panelEpics(summaries, false).map((s) => s.id)).toEqual([
            parentOpen.id,
        ]);
    });

    it('keeps the focused epic listed after it finishes', () => {
        expect(
            panelEpics(summaries, false, parentDone.id).map((s) => s.id),
        ).toEqual([parentOpen.id, parentDone.id]);
    });

    it('shows finished and empty epics when asked', () => {
        expect(panelEpics(summaries, true).map((s) => s.id)).toEqual([
            parentOpen.id,
            parentDone.id,
            parentEmpty.id,
        ]);
    });
});

describe('matchesEpicFocus', () => {
    const parent = epic();
    const other = epic();
    const child = childOf(parent);
    const otherChild = childOf(other);
    const loose = issue();

    it('lets everything through without a focus', () => {
        for (const candidate of [parent, child, loose]) {
            expect(matchesEpicFocus(candidate, null)).toBe(true);
        }
    });

    it('keeps only the children of the focused epic', () => {
        expect(matchesEpicFocus(child, parent.id)).toBe(true);
        expect(matchesEpicFocus(otherChild, parent.id)).toBe(false);
        expect(matchesEpicFocus(loose, parent.id)).toBe(false);
        expect(matchesEpicFocus(parent, parent.id)).toBe(false);
    });

    it('keeps loose issues for the no-epic focus, never epics', () => {
        expect(matchesEpicFocus(loose, 'none')).toBe(true);
        expect(matchesEpicFocus(child, 'none')).toBe(false);
        expect(matchesEpicFocus(parent, 'none')).toBe(false);
    });
});

describe('groupEpicsByProject', () => {
    it('groups epics under their project in first-seen order', () => {
        const alpha = { key: 'ALP', name: 'Alpha' };
        const beta = { key: 'BET', name: 'Beta' };
        const a1 = epic({ project: alpha });
        const b1 = epic({ project: beta });
        const a2 = epic({ project: alpha });

        const groups = groupEpicsByProject(
            summarizeEpics([a1, b1, a2], legacyLanes),
        );

        expect(
            groups.map((g) => [g.key, g.name, g.epics.map((e) => e.id)]),
        ).toEqual([
            ['ALP', 'Alpha', [a1.id, a2.id]],
            ['BET', 'Beta', [b1.id]],
        ]);
    });

    it('collects epics without a known project under an unnamed group', () => {
        const absent = issue();

        const groups = groupEpicsByProject(
            summarizeEpics([childOf(absent)], legacyLanes),
        );

        expect(groups).toEqual([
            {
                key: '',
                name: '',
                epics: [expect.objectContaining({ id: absent.id })],
            },
        ]);
    });
});

describe('canDropOnEpic', () => {
    const summary = (parent: Issue) => summarizeEpics([parent], legacyLanes)[0];

    it('accepts an epic in the same project', () => {
        expect(canDropOnEpic(issue(), summary(epic()))).toBe(true);
    });

    it('refuses an epic in another project', () => {
        const other = epic({ project: { key: 'OTH', name: 'Other' } });

        expect(canDropOnEpic(issue(), summary(other))).toBe(false);
    });

    it('refuses an epic whose project the board does not know', () => {
        const absent = issue();
        const [unknown] = summarizeEpics([childOf(absent)], legacyLanes);

        expect(canDropOnEpic(issue(), unknown)).toBe(false);
    });

    it('refuses dropping a card onto itself', () => {
        const card = epic();

        expect(canDropOnEpic(card, summary(card))).toBe(false);
    });

    it('refuses every epic for a card that has sub-issues', () => {
        const card = issue({ childrenCount: 2 });

        expect(canDropOnEpic(card, summary(epic()))).toBe(false);
    });

    it('always accepts the no-epic row', () => {
        expect(canDropOnEpic(issue(), 'none')).toBe(true);
        expect(canDropOnEpic(issue({ childrenCount: 2 }), 'none')).toBe(true);
    });
});

describe('parentAfterDrop', () => {
    it('links the card to the epic it was dropped on', () => {
        const parent = epic();
        const [target] = summarizeEpics([parent], legacyLanes);

        expect(parentAfterDrop(issue(), target)).toEqual({
            parent: parent.identifier,
        });
    });

    it('detaches the card when dropped on the no-epic row', () => {
        const card = childOf(epic());

        expect(parentAfterDrop(card, 'none')).toEqual({ parent: null });
    });

    it('is a no-op on the epic the card already belongs to', () => {
        const parent = epic();
        const [target] = summarizeEpics([parent], legacyLanes);

        expect(parentAfterDrop(childOf(parent), target)).toBeNull();
    });

    it('is a no-op when a loose card is dropped on the no-epic row', () => {
        expect(parentAfterDrop(issue(), 'none')).toBeNull();
    });

    it('is a no-op on an invalid target', () => {
        const other = epic({ project: { key: 'OTH', name: 'Other' } });
        const [target] = summarizeEpics([other], legacyLanes);

        expect(parentAfterDrop(issue(), target)).toBeNull();
    });
});

describe('undoParentChange', () => {
    it('restores the epic the card had before the drop', () => {
        const parent = epic();

        expect(undoParentChange(childOf(parent))).toEqual({
            parent: parent.identifier,
        });
    });

    it('restores no epic for a card that was loose', () => {
        expect(undoParentChange(issue())).toEqual({ parent: null });
    });
});
