export interface ProjectRepoLink {
    name: string;
    url: string;
}

export interface ProjectLinks {
    docs: string | null;
    production: string | null;
    repos: ProjectRepoLink[];
}

export interface Team {
    id: number;
    key: string;
    name: string;
    color: string;
    githubRepos: string[];
    productionUrl: string | null;
    links: ProjectLinks;
    issuesCount: number;
    keyLocked: boolean;
}
