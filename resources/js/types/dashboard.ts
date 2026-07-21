export type DashboardView = 'focus' | 'metrics' | 'board';

export type IssueStatusKey = 'backlog' | 'in_progress' | 'in_review' | 'done';

export interface DashboardStats {
    open: number;
    in_progress: number;
    in_review: number;
    done: number;
    archived: number;
}

export interface StatusBreakdown {
    backlog: number;
    in_progress: number;
    in_review: number;
    done: number;
}

export interface ActiveByProject {
    key: string;
    name: string;
    color: string;
    count: number;
}

export interface IssueRow {
    identifier: string;
    title: string;
    projectName: string;
    projectColor: string;
    status: IssueStatusKey;
    ageDays: number;
    stale: boolean;
    timestamp: string | null;
}

export interface BoardColumns {
    backlog: IssueRow[];
    in_progress: IssueRow[];
    in_review: IssueRow[];
    done: IssueRow[];
}

export interface TrendPoint {
    label: string;
    opened: number;
    completed: number;
    cycle: number | null;
}

export interface DashboardMetrics {
    completed: number;
    completedDelta: number;
    opened: number;
    openedDelta: number;
    wip: number;
    cycleDays: number | null;
    cycleDelta: number | null;
    completedSpark: number[];
    openedSpark: number[];
    cycleSpark: number[];
}
