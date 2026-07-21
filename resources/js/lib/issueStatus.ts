import type { IssueStatusKey } from '@/types';

export const statusDotClass: Record<IssueStatusKey, string> = {
    backlog: 'bg-muted-foreground/50',
    in_progress: 'bg-primary',
    in_review: 'bg-sky-500',
    done: 'bg-emerald-500',
};

export const statusChipClass: Record<IssueStatusKey, string> = {
    backlog: 'bg-muted text-muted-foreground',
    in_progress: 'bg-primary/10 text-primary',
    in_review: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    done: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
};

export const staleChipClass =
    'bg-amber-500/10 text-amber-600 dark:text-amber-400';
