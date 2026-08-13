export type IssueStatusKey = 'backlog' | 'in_progress' | 'in_review' | 'done';

export type IssuePriorityKey = 'none' | 'low' | 'medium' | 'high' | 'urgent';

export interface DashboardStats {
    open: number;
    in_progress: number;
    in_review: number;
    done: number;
    archived: number;
    urgentOpen: number;
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
    color: string | null;
    count: number;
    other: boolean;
}

export interface IssueRow {
    identifier: string;
    title: string;
    projectName: string;
    projectColor: string;
    status: IssueStatusKey;
    priority: IssuePriorityKey;
    ageDays: number;
    stale: boolean;
    timestamp: string | null;
}

/** A stale row carries how long it has been quiet, on top of the usual fields. */
export interface StaleRow extends IssueRow {
    quietSince: string;
    quietDays: number;
}

export interface CompletedSeries {
    key: string;
    name: string;
    color: string | null;
    values: number[];
    total: number;
    other: boolean;
}

export interface CompletedByWeek {
    weeks: string[];
    series: CompletedSeries[];
    weekTotals: number[];
    grandTotal: number;
}

export interface DashboardMetrics {
    completed: number;
    completedDelta: number;
    wip: number;
    cycleDays: number | null;
    cycleDelta: number | null;
    completedSpark: number[];
    cycleSpark: number[];
}

export interface LoggedProject {
    key: string;
    name: string;
    color: string;
    minutes: number;
}

export interface EstimateAccuracy {
    pct: number | null;
    overPct: number | null;
    direction: 'over' | 'under' | 'none';
    sampleSize: number;
}

export interface DashboardTime {
    loggedThisWeek: number;
    loggedPreviousWeek: number;
    loggedByProject: LoggedProject[];
    accuracy: EstimateAccuracy;
}
