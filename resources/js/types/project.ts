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
