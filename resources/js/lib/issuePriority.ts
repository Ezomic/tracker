import type { IssuePriorityKey } from '@/types';

/** Chip styling for the priorities worth flagging on the dashboard. */
export const priorityChipClass: Partial<Record<IssuePriorityKey, string>> = {
    urgent: 'bg-destructive/10 text-destructive',
    high: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
};

export function priorityChip(priority: IssuePriorityKey): string | null {
    return priorityChipClass[priority] ?? null;
}
