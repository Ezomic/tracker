export interface DashboardStats {
    open: number;
    in_progress: number;
    in_review: number;
    done: number;
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

export interface DashboardRow {
    identifier: string;
    title: string;
    projectColor: string;
    status: 'backlog' | 'in_progress' | 'in_review' | 'done';
    timestamp: string | null;
}
