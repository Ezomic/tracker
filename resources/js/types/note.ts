export interface NoteProject {
    key: string;
    name: string;
}

export interface NoteSummary {
    id: number;
    title: string;
    author: string | null;
    updatedAt: string;
}

export interface BoardNote {
    id: number;
    title: string;
    projectKey: string;
    projectName: string;
    updatedAt: string;
}

export interface NoteDetail {
    id: number;
    title: string;
    body: string;
    author: string | null;
    createdAt: string;
    updatedAt: string;
}
