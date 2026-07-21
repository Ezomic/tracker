import { beforeEach, describe, expect, it } from 'vitest';
import { useCommandPalette } from './useCommandPalette';

describe('useCommandPalette', () => {
    beforeEach(() => {
        useCommandPalette().close();
    });

    it('starts closed', () => {
        expect(useCommandPalette().open.value).toBe(false);
    });

    it('toggle flips the open state', () => {
        const palette = useCommandPalette();

        palette.toggle();
        expect(palette.open.value).toBe(true);

        palette.toggle();
        expect(palette.open.value).toBe(false);
    });

    it('show and close set the state explicitly', () => {
        const palette = useCommandPalette();

        palette.show();
        expect(palette.open.value).toBe(true);

        palette.close();
        expect(palette.open.value).toBe(false);
    });

    it('shares state across call sites', () => {
        useCommandPalette().show();
        expect(useCommandPalette().open.value).toBe(true);
    });
});
