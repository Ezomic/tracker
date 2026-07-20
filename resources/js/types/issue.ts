export interface IssueUser {
    id: number;
    name: string;
    email: string;
}

export interface TimeEntry {
    id: number;
    minutes: number;
    note: string | null;
    spentOn: string;
    user: IssueUser | null;
}

export interface IssueComment {
    id: number;
    body: string;
    createdAt: string;
    user: IssueUser | null;
}

export type TimelineItem =
    | {
          kind: 'comment';
          id: number;
          createdAt: string;
          user: IssueUser | null;
          body: string;
      }
    | {
          kind: 'activity';
          id: number;
          createdAt: string;
          user: IssueUser | null;
          type: string;
          data: Record<string, string | number | null> | null;
      }
    | {
          kind: 'commit';
          id: number;
          createdAt: string;
          sha: string;
          shortSha: string;
          message: string;
          url: string | null;
          authorName: string | null;
      };

export interface Issue {
    identifier: string;
    title: string;
    description: string | null;
    estimateMinutes: number | null;
    loggedMinutes: number;
    invoiceable: boolean;
    confirmedMinutes: number | null;
    confirmedAt: string | null;
    type: 'feature' | 'fix';
    priority: 'none' | 'low' | 'medium' | 'high' | 'urgent';
    status: 'backlog' | 'in_progress' | 'in_review' | 'done';
    branchName: string;
    branchUrl: string | null;
    commitsUrl: string | null;
    githubPrUrl: string | null;
    project: {
        key: string;
        name: string;
        billrLinked: boolean;
    };
    owner: IssueUser | null;
    assignee: IssueUser | null;
    createdAt: string;
    archivedAt: string | null;
    archiveReason: string | null;
    childrenCount: number;
    childrenDoneCount: number;
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
    timeEntries: TimeEntry[];
    comments: IssueComment[];
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
    project_id: number | null;
    status: Issue['status'] | null;
    type: Issue['type'] | null;
    priority: Issue['priority'] | null;
    label_id: number | null;
}

export interface SavedView {
    id: number;
    name: string;
    project_id: number | null;
    criteria: Partial<IssueFilters>;
}

export interface AppNotification {
    id: string;
    read: boolean;
    createdAt: string | null;
    data: {
        type: 'issue_assigned' | 'comment_mention' | 'issue_commented';
        issueIdentifier: string;
        issueTitle: string;
        actorName: string;
        excerpt: string | null;
    };
}

export type Cadence = 'none' | 'daily' | 'weekly' | 'monthly';

export interface IssueTemplate {
    id: number;
    name: string;
    description: string | null;
    type: Issue['type'] | null;
    priority: Issue['priority'] | null;
    labelIds: number[];
    cadence: Cadence;
    nextRunAt: string | null;
    targetProjectId: number | null;
}
