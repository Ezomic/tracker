import type { Ref } from 'vue';
import { ref, watch } from 'vue';

function storageKey(userId: number): string {
    return `board.epicPanel.collapsed.${userId}`;
}

// Storage can be blocked (private mode, site data off); the panel then simply
// forgets its state between visits instead of breaking the board.
function read(key: string): boolean {
    try {
        return localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function write(key: string, collapsed: boolean): void {
    try {
        localStorage.setItem(key, collapsed ? '1' : '0');
    } catch {
        // Nothing to do: the in-memory value still drives this visit.
    }
}

export function useEpicPanelCollapsed(userId: number): Ref<boolean> {
    const key = storageKey(userId);
    const collapsed = ref(read(key));

    watch(collapsed, (value) => write(key, value));

    return collapsed;
}
