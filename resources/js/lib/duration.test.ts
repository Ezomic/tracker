import { describe, expect, it } from 'vitest';
import { formatDuration } from './duration';

describe('formatDuration', () => {
    it('returns "0m" for zero or negative input', () => {
        expect(formatDuration(0)).toBe('0m');
        expect(formatDuration(-30)).toBe('0m');
    });

    it('formats sub-hour durations as minutes only', () => {
        expect(formatDuration(45)).toBe('45m');
        expect(formatDuration(1)).toBe('1m');
        expect(formatDuration(59)).toBe('59m');
    });

    it('drops the minute part on whole hours', () => {
        expect(formatDuration(60)).toBe('1h');
        expect(formatDuration(120)).toBe('2h');
    });

    it('combines hours and minutes', () => {
        expect(formatDuration(90)).toBe('1h 30m');
        expect(formatDuration(605)).toBe('10h 5m');
    });
});
