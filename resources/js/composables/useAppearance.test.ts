import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Appearance } from './useAppearance';
import { updateTheme, useAppearance } from './useAppearance';

function mockMatchMedia(matches: boolean) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn().mockReturnValue({
            matches,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }),
    );
}

describe('useAppearance', () => {
    beforeEach(() => {
        localStorage.clear();
        document.cookie = '';
        document.documentElement.classList.remove('dark');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('updateTheme toggles the dark class for an explicit value', () => {
        updateTheme('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        updateTheme('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('updateTheme follows the system preference when set to system', () => {
        mockMatchMedia(true);
        updateTheme('system');
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        mockMatchMedia(false);
        updateTheme('system');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('resolvedAppearance follows the system preference', () => {
        mockMatchMedia(true);
        const { resolvedAppearance, updateAppearance } = useAppearance();

        updateAppearance('system');
        expect(resolvedAppearance.value).toBe('dark');
    });

    it('resolvedAppearance mirrors an explicit choice', () => {
        mockMatchMedia(false);
        const { resolvedAppearance, updateAppearance } = useAppearance();

        updateAppearance('light');
        expect(resolvedAppearance.value).toBe('light');
    });

    it('updateAppearance persists to localStorage and cookie', () => {
        mockMatchMedia(false);
        const { updateAppearance } = useAppearance();

        updateAppearance('dark' as Appearance);

        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');
    });
});
