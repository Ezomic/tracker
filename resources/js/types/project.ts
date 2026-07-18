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

export type ProjectLevel = 'read' | 'write' | 'admin';

export type OrganizationRole = 'owner' | 'admin' | 'member' | 'guest';

export interface ProjectMember {
    id: number;
    name: string;
    email: string;
    level: ProjectLevel;
}

export interface AssignableMember {
    id: number;
    name: string;
    email: string;
}

export interface OrganizationMember {
    id: number;
    name: string;
    email: string;
    role: OrganizationRole;
}

export interface OrganizationInvitation {
    id: number;
    email: string;
    role: OrganizationRole;
    projectName: string | null;
    level: ProjectLevel | null;
    expiresAt: string;
}

export interface Project {
    id: number;
    key: string;
    name: string;
    description: string | null;
    color: string;
    level: ProjectLevel;
    isFavorite: boolean;
    githubRepos: string[];
    productionUrl: string | null;
    archiveAfterDays: number | null;
    links: ProjectLinks;
    openCount: number;
    issuesCount: number;
    loggedMinutes: number;
    keyLocked: boolean;
}

export interface OrganizationSummary {
    id: number;
    name: string;
    slug: string;
    canManage: boolean;
    canViewLibrary: boolean;
}
