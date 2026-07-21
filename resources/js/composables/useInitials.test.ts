import { describe, expect, it } from 'vitest';
import { getInitials, useInitials } from './useInitials';

describe('getInitials', () => {
    it('returns an empty string for missing or blank names', () => {
        expect(getInitials()).toBe('');
        expect(getInitials('')).toBe('');
        expect(getInitials('   ')).toBe('');
    });

    it('uppercases the single initial of a one-word name', () => {
        expect(getInitials('robbin')).toBe('R');
    });

    it('takes the first and last initial of a multi-word name', () => {
        expect(getInitials('Robbin Thijssen')).toBe('RT');
        expect(getInitials('Ada King Lovelace')).toBe('AL');
    });

    it('collapses irregular whitespace between names', () => {
        expect(getInitials('  Robbin   Thijssen  ')).toBe('RT');
    });

    it('handles unicode first characters', () => {
        expect(getInitials('émile zola')).toBe('ÉZ');
    });
});

describe('useInitials', () => {
    it('exposes getInitials', () => {
        expect(useInitials().getInitials('Robbin Thijssen')).toBe('RT');
    });
});
