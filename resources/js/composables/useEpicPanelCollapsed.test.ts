import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { useEpicPanelCollapsed } from './useEpicPanelCollapsed';

describe('useEpicPanelCollapsed', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('starts expanded when nothing is stored', () => {
        expect(useEpicPanelCollapsed(1).value).toBe(false);
    });

    it('remembers the choice per user', async () => {
        const collapsed = useEpicPanelCollapsed(1);
        collapsed.value = true;
        await nextTick();

        expect(useEpicPanelCollapsed(1).value).toBe(true);
        expect(useEpicPanelCollapsed(2).value).toBe(false);
    });

    it('stores expanding again', async () => {
        localStorage.setItem('board.epicPanel.collapsed.1', '1');
        const collapsed = useEpicPanelCollapsed(1);
        collapsed.value = false;
        await nextTick();

        expect(useEpicPanelCollapsed(1).value).toBe(false);
    });

    it('falls back to expanded when storage cannot be read', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        expect(useEpicPanelCollapsed(1).value).toBe(false);
    });

    it('keeps working in memory when storage cannot be written', async () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        const collapsed = useEpicPanelCollapsed(1);
        collapsed.value = true;
        await nextTick();

        expect(collapsed.value).toBe(true);
    });
});
