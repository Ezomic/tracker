export interface ProjectLinks {
    docs: string | null;
    readme: string | null;
    production: string | null;
}

export interface Team {
    id: number;
    key: string;
    name: string;
    color: string;
    githubRepo: string | null;
    productionUrl: string | null;
    links: ProjectLinks;
    issuesCount: number;
    keyLocked: boolean;
}
