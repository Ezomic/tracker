export interface Issue {
    identifier: string;
    title: string;
    description: string | null;
    type: 'feature' | 'fix';
    priority: 'none' | 'low' | 'medium' | 'high' | 'urgent';
    status: 'backlog' | 'in_progress' | 'in_review' | 'done';
    branchName: string;
    githubPrUrl: string | null;
    team: {
        key: string;
        name: string;
    };
    createdAt: string;
    archivedAt: string | null;
    childrenCount: number;
    parent: {
        id: number;
        identifier: string;
        title: string;
    } | null;
    children: {
        identifier: string;
        title: string;
        status: Issue['status'];
    }[];
    labels: IssueLabel[];
}

export interface EpicOption {
    id: number;
    identifier: string;
    title: string;
}

export type LabelColor =
    'gray' | 'red' | 'yellow' | 'green' | 'blue' | 'purple';

export interface IssueLabel {
    id: number;
    name: string;
    color: LabelColor;
}

export interface IssueFilters {
    search: string | null;
    team_id: number | null;
    status: Issue['status'] | null;
    type: Issue['type'] | null;
    priority: Issue['priority'] | null;
    label_id: number | null;
}
