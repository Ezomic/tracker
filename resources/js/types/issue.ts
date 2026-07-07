export interface Issue {
    identifier: string;
    title: string;
    description: string | null;
    type: 'feature' | 'fix';
    status: 'backlog' | 'in_progress' | 'in_review' | 'done';
    branchName: string;
    githubPrUrl: string | null;
    team: {
        key: string;
        name: string;
    };
    createdAt: string;
}
