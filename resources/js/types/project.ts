export interface SidebarProjectCounts {
    backlog: number;
    in_progress: number;
    in_review: number;
    done: number;
}

export interface SidebarProject {
    id: number;
    key: string;
    name: string;
    color: string;
    counts: SidebarProjectCounts;
}

export interface ProjectRepoLink {
    name: string;
    url: string;
}

export interface ProjectLinks {
    docs: string | null;
    production: string | null;
    repos: ProjectRepoLink[];
}

export interface Project {
    id: number;
    key: string;
    name: string;
    description: string | null;
    color: string;
    isFavorite: boolean;
    githubRepos: string[];
    productionUrl: string | null;
    archiveAfterDays: number | null;
    links: ProjectLinks;
    openCount: number;
    issuesCount: number;
    keyLocked: boolean;
}
